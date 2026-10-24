<?php

namespace App\Libraries;

use App\Models\SettingModel;
use CodeIgniter\Email\Email;
use Throwable;

/**
 * Sends league email through the email server set on the Settings page.
 */
class Mailer
{
    /**
     * The settings row, or [] if there is none yet.
     */
    public static function settings(): array
    {
        return (new SettingModel())->first() ?? [];
    }

    /**
     * True when the server and From address are set, so email can be sent.
     */
    public static function isConfigured(?array $settings = null): bool
    {
        $settings ??= self::settings();

        return ! empty($settings['smtp_host']) && ! empty($settings['mail_from_email']);
    }

    /**
     * The SMTP password, stored encrypted with the app's key.
     */
    public static function encryptPassword(string $password): string
    {
        return base64_encode(service('encrypter')->encrypt($password));
    }

    public static function decryptPassword(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }

        try {
            return service('encrypter')->decrypt(base64_decode($stored, true) ?: '');
        } catch (Throwable) {
            // Saved with a different key: treat as no password.
            return '';
        }
    }

    /**
     * The email service set up for the league's server. Call message()
     * before each message.
     */
    public static function email(?array $settings = null): Email
    {
        $settings ??= self::settings();
        $email      = service('email');
        $email->initialize([
            'protocol'      => 'smtp',
            'SMTPHost'      => (string) $settings['smtp_host'],
            'SMTPPort'      => (int) ($settings['smtp_port'] ?? 0 ?: 587),
            'SMTPCrypto'    => in_array($settings['smtp_crypto'] ?? 'tls', ['tls', 'ssl'], true) ? ($settings['smtp_crypto'] ?? 'tls') : '',
            'SMTPUser'      => (string) ($settings['smtp_user'] ?? ''),
            'SMTPPass'      => self::decryptPassword($settings['smtp_pass_encrypted'] ?? null),
            'SMTPTimeout'   => 15,
            // One connection for a whole batch of messages.
            'SMTPKeepAlive' => true,
            'mailType'      => 'text',
        ]);

        return $email;
    }

    /**
     * Clears the previous message (and its attachments) and sets the From
     * address, with replies going to the admin contact if there is one.
     */
    public static function message(Email $email, array $settings): Email
    {
        $email->clear(true);
        $email->setFrom((string) $settings['mail_from_email'], (string) ($settings['mail_from_name'] ?? ''));
        if (! empty($settings['admin_contact_email'])) {
            $email->setReplyTo($settings['admin_contact_email'], (string) ($settings['admin_contact_name'] ?? ''));
        }

        return $email;
    }
}
