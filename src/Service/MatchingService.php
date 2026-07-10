<?php

namespace App\Service;

use App\Entity\Offre;
use App\Entity\ProfilCandidat;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MatchingService
 *
 * Calcule le score de matching IA (RM-M01 à RM-M06) entre le profil enrichi
 * d'un candidat (déjà parsé par CvAiProfileAnalyzer) et une offre.
 *
 * Principe de robustesse identique à CvAiProfileAnalyzer : un échec (API
 * indisponible, JSON invalide, clé manquante...) ne bloque JAMAIS le dépôt
 * de la candidature — le score reste simplement null (RM-M03).
 */
class MatchingService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $groqApiKey,
    ) {}

    /**
     * @return array{score: ?int, competences_matchees: string[], competences_manquantes: string[]}
     */
    public function computeScore(ProfilCandidat $candidat, Offre $offre): array
    {
        $default = [
            'score' => null,
            'competences_matchees' => [],
            'competences_manquantes' => [],
        ];

        if (empty($this->groqApiKey)) {
            $this->logger->warning('MatchingService: GROQ_API_KEY non configurée — scoring IA ignoré.');
            return $default;
        }

        try {
            return array_merge($default, $this->callAi($candidat, $offre));
        } catch (\Throwable $e) {
            $this->logger->error('MatchingService: échec du calcul du score IA — ' . $e->getMessage());
            return $default;
        }
    }

    private function callAi(ProfilCandidat $candidat, Offre $offre): array
    {
        $systemPrompt = <<<PROMPT
Tu es un moteur de matching entre un profil candidat et une offre de stage/emploi pour la
plateforme MatchCV. Réponds UNIQUEMENT avec un objet JSON valide — sans texte avant/après,
sans bloc markdown, sans balise ```json — respectant strictement ce schéma :

{
  "score": <entier de 0 à 100>,
  "competences_matchees": [<chaînes>],
  "competences_manquantes": [<chaînes>]
}

Règles strictes :
- "score" : pourcentage global d'adéquation entre le profil du candidat et les exigences de
  l'offre, basé sur les compétences techniques, les années d'expérience, les formations et les
  expériences professionnelles/projets du candidat comparés aux compétences requises et au
  type de poste. N'invente rien : si peu d'informations sont disponibles, reste prudent.
- "competences_matchees" : compétences requises par l'offre que le candidat possède réellement.
- "competences_manquantes" : compétences requises par l'offre que le candidat ne possède pas.
- Si l'offre ne liste aucune compétence requise, retourne des listes vides et base le score
  uniquement sur l'adéquation générale (expérience, formation, type de contrat).
PROMPT;

        $userContent = "=== Offre ===\n"
            . 'Titre : ' . $offre->getTitre() . "\n"
            . 'Description : ' . $offre->getDescription() . "\n"
            . 'Compétences requises : ' . (implode(', ', $offre->getCompetencesRequisesArray()) ?: '(non précisées)') . "\n"
            . 'Type de contrat : ' . $offre->getTypeContratLabel() . "\n"
            . 'Mode de travail : ' . $offre->getModeTravailLabel() . "\n\n"
            . "=== Profil candidat ===\n"
            . 'Années d\'expérience : ' . ($candidat->getAnneesExperience() ?? 'Non renseigné') . "\n"
            . 'Compétences techniques : ' . (implode(', ', $candidat->getCompetencesTechniquesArray()) ?: '(aucune détectée)') . "\n"
            . 'Formations : ' . (implode(', ', $candidat->getFormationsArray()) ?: '(aucune détectée)') . "\n"
            . 'Expériences professionnelles : ' . (implode(', ', $candidat->getExperiencesProfessionnellesArray()) ?: '(aucune détectée)') . "\n"
            . 'Projets académiques : ' . (implode(', ', $candidat->getProjetsAcademiquesArray()) ?: '(aucun détecté)') . "\n"
            . 'Soft skills : ' . (implode(', ', $candidat->getSoftSkillsArray()) ?: '(aucun détecté)') . "\n"
            . 'Résumé IA du profil : ' . ($candidat->getResumeIa() ?? 'Non disponible');

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->groqApiKey,
            ],
            'json' => [
                'model' => self::MODEL,
                'temperature' => 0.1,
                'max_tokens' => 800,
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

        $score = isset($decoded['score']) && is_numeric($decoded['score'])
            ? max(0, min(100, (int) round($decoded['score'])))
            : null;

        return [
            'score' => $score,
            'competences_matchees' => $this->sanitizeStringList($decoded['competences_matchees'] ?? null),
            'competences_manquantes' => $this->sanitizeStringList($decoded['competences_manquantes'] ?? null),
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