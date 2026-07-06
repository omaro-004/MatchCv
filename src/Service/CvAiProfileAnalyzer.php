<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * CvAiProfileAnalyzer
 *
 * Remplace le "simple GET" des données du formulaire d'inscription par une
 * véritable analyse IA du profil candidat, combinant :
 *   1. Le texte brut extrait du CV PDF uploadé (via smalot/pdfparser).
 *   2. Les données déjà saisies dans le formulaire d'inscription (bio,
 *      localisation, type de contrat...).
 *
 * ── IA utilisée : GROQ (gratuit) ──────────────────────────────────────
 * Groq expose une API 100% GRATUITE (compte sur https://console.groq.com,
 * aucune carte bancaire requise) compatible avec le format "OpenAI Chat
 * Completions". Elle héberge des modèles open-source (Llama 3.3) avec des
 * temps de réponse très rapides — largement suffisants pour une tâche
 * d'extraction structurée comme celle-ci.
 *
 * L'IA retourne un JSON strict permettant d'alimenter automatiquement les
 * champs de ProfilCandidat :
 *   - annees_experience
 *   - langues_parlees
 *   - competences_techniques (langages / frameworks / outils)
 *   - formations
 *   - experiences_professionnelles
 *   - resume_ia
 *
 * Principe de robustesse : AUCUNE étape de ce service ne doit jamais
 * bloquer l'inscription ou la complétion de profil. Toute erreur (PDF
 * illisible, CV scanné sans couche texte, API indisponible, quota
 * dépassé, JSON invalide...) est loggée et remplacée par des valeurs
 * vides — conformément à la consigne : "si le PDF ne contient pas les
 * infos nécessaires, on laisse les champs vides".
 */
class CvAiProfileAnalyzer
{
    // Endpoint Groq, compatible format OpenAI Chat Completions.
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    // Modèle gratuit, rapide, bonne qualité pour de l'extraction structurée.
    // Autre modèle gratuit disponible sur Groq si besoin de plus de rapidité :
    // 'llama-3.1-8b-instant' (plus rapide, un peu moins précis).
    private const MODEL = 'llama-3.3-70b-versatile';

    private const MAX_CV_CHARS = 12000; // limite pour garder un prompt raisonnable

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $groqApiKey,
    ) {}

    /**
     * @param string $absoluteCvPath Chemin ABSOLU vers le fichier PDF du CV sur le disque
     * @param array{nom_complet?: ?string, bio?: ?string, localisation?: ?string, type_contrat?: ?string} $formData
     *
     * @return array{
     *     annees_experience: ?int,
     *     langues_parlees: string[],
     *     competences_techniques: string[],
     *     formations: string[],
     *     experiences_professionnelles: string[],
     *     resume_ia: ?string
     * }
     */
    public function analyze(string $absoluteCvPath, array $formData): array
    {
        $default = [
            'annees_experience' => null,
            'langues_parlees' => [],
            'competences_techniques' => [],
            'formations' => [],
            'experiences_professionnelles' => [],
            'projets_academiques' => [],
            'soft_skills' => [],
            'resume_ia' => null,
        ];

        if (empty($this->groqApiKey)) {
            $this->logger->warning('CvAiProfileAnalyzer: GROQ_API_KEY non configurée — analyse IA ignorée.');
            return $default;
        }

        $cvText = $this->extractTextFromPdf($absoluteCvPath);

        if ($cvText === null) {
            $this->logger->info('CvAiProfileAnalyzer: aucun texte exploitable extrait du CV (PDF scanné/image ?).', [
                'path' => $absoluteCvPath,
            ]);
            $cvText = '';
        }

        // Si ni CV ni bio ne fournissent de matière, inutile d'appeler l'IA.
        if ($cvText === '' && empty($formData['bio'])) {
            return $default;
        }

        try {
            return array_merge($default, $this->callAi($cvText, $formData));
        } catch (\Throwable $e) {
            $this->logger->error('CvAiProfileAnalyzer: échec de l\'analyse IA — ' . $e->getMessage());
            return $default;
        }
    }

    private function extractTextFromPdf(string $absolutePath): ?string
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($absolutePath);
            $text = trim(preg_replace('/\s+/', ' ', $pdf->getText()));

            if ($text === '') {
                return null;
            }

            return mb_substr($text, 0, self::MAX_CV_CHARS);
        } catch (\Throwable $e) {
            $this->logger->warning('CvAiProfileAnalyzer: erreur extraction PDF — ' . $e->getMessage());
            return null;
        }
    }

    private function callAi(string $cvText, array $formData): array
    {
        $systemPrompt = <<<PROMPT
Tu es un moteur d'extraction d'informations de CV pour la plateforme MatchCV.
Analyse le texte du CV fourni ainsi que les informations du formulaire
d'inscription, puis réponds UNIQUEMENT avec un objet JSON valide — sans
texte avant/après, sans bloc markdown, sans balise ```json — respectant
strictement ce schéma :

{
  "annees_experience": <entier ou null>,
  "langues_parlees": [<chaînes>],
  "competences_techniques": [<chaînes>],
  "formations": [<chaînes>],
  "experiences_professionnelles": [<chaînes>],
  "projets_academiques": [<chaînes>],
  "soft_skills": [<chaînes>],
  "resume_ia": <chaîne ou null>
}

Règles strictes :
- N'invente RIEN. Si une information n'est pas présente ou pas déductible
  avec confiance, utilise null (champs scalaires) ou [] (listes).
- "annees_experience" : nombre total d'années d'expérience professionnelle
  pertinente, déduit des dates d'expérience mentionnées dans le CV.
- "langues_parlees" : langues humaines parlées (ex: "Français (natif)",
  "Anglais (courant)") — jamais les langages de programmation.
- "competences_techniques" : langages de programmation, frameworks,
  bibliothèques, outils et technologies mentionnés dans le CV.
- "formations" : diplômes / cursus académiques (intitulé + établissement).
- "experiences_professionnelles" : un élément court par expérience
  (intitulé du poste — entreprise — durée si disponible).
- "projets_academiques" : projets réalisés dans un cadre scolaire/universitaire
  ou personnel (hackathons, PFE, mini-projets...). Un élément court par
  projet (intitulé — technologies utilisées — brève description si
  disponible). Ne pas y inclure les expériences professionnelles/stages.
- "soft_skills" : compétences comportementales / interpersonnelles
  explicitement mentionnées dans le CV (ex: "Travail en équipe",
  "Communication", "Gestion du temps", "Esprit critique", "Leadership").
  Ne pas y inclure les compétences techniques.
- "resume_ia" : résumé professionnel synthétique en français, 2 à 3
  phrases maximum, basé uniquement sur les faits du CV.
PROMPT;

        $userContent = "=== Données du formulaire d'inscription ===\n"
            . 'Nom complet : ' . ($formData['nom_complet'] ?? '') . "\n"
            . 'Bio fournie par le candidat : ' . (!empty($formData['bio']) ? $formData['bio'] : '(non renseignée)') . "\n"
            . 'Localisation : ' . ($formData['localisation'] ?? '') . "\n"
            . 'Type de contrat recherché : ' . ($formData['type_contrat'] ?? '') . "\n\n"
            . "=== Texte extrait du CV (PDF) ===\n"
            . ($cvText !== '' ? $cvText : '(aucun texte exploitable extrait du PDF — base-toi uniquement sur le formulaire)');

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->groqApiKey,
            ],
            'json' => [
                'model' => self::MODEL,
                'temperature' => 0.2,
                'max_tokens' => 1500,
                // Mode JSON forcé — évite les préambules/texte parasite.
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
            ],
            'timeout' => 30,
        ]);

        $data = $response->toArray(false);

        $text = $data['choices'][0]['message']['content'] ?? '';

        $clean = trim(preg_replace('/^```json|```$/m', '', $text));
        $decoded = json_decode($clean, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Réponse IA non exploitable (JSON invalide) : ' . substr($text, 0, 300));
        }

        return [
            'annees_experience' => isset($decoded['annees_experience']) && is_numeric($decoded['annees_experience'])
                ? max(0, (int) $decoded['annees_experience'])
                : null,
            'langues_parlees' => $this->sanitizeStringList($decoded['langues_parlees'] ?? null),
            'competences_techniques' => $this->sanitizeStringList($decoded['competences_techniques'] ?? null),
            'formations' => $this->sanitizeStringList($decoded['formations'] ?? null),
            'experiences_professionnelles' => $this->sanitizeStringList($decoded['experiences_professionnelles'] ?? null),
            'projets_academiques' => $this->sanitizeStringList($decoded['projets_academiques'] ?? null),
            'soft_skills' => $this->sanitizeStringList($decoded['soft_skills'] ?? null),
            'resume_ia' => is_string($decoded['resume_ia'] ?? null) && trim($decoded['resume_ia']) !== ''
                ? trim($decoded['resume_ia'])
                : null,
        ];
    }

    /** @return string[] */
    private function sanitizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : null,
            $value
        ), static fn ($v) => !empty($v)));
    }
}