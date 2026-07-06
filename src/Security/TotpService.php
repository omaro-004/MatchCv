<?php

namespace App\Security;

use OTPHP\TOTP;

/**
 * TotpService
 *
 * Encapsule la génération et la vérification des codes à usage unique
 * (TOTP — RFC 6238), compatibles Google Authenticator, Authy, Microsoft
 * Authenticator, etc.
 */
class TotpService
{
    private const ISSUER = 'MatchCV';

    /**
     * Génère un nouveau secret aléatoire (base32), non encore persisté.
     */
    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    /**
     * Construit l'URI otpauth:// à encoder en QR code, que l'application
     * d'authentification va scanner pour se configurer automatiquement.
     */
    public function getProvisioningUri(string $secret, string $accountLabel): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($accountLabel);
        $totp->setIssuer(self::ISSUER);

        return $totp->getProvisioningUri();
    }

    /**
     * Vérifie qu'un code à 6 chiffres saisi par l'utilisateur correspond
     * bien au secret enregistré (fenêtre de validité de 30 secondes).
     */
    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);

        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);

        return $totp->verify($code);
    }
}