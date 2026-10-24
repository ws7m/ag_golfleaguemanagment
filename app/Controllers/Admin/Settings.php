<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Mailer;
use App\Models\PlayerModel;
use App\Models\SettingModel;

/**
 * Site settings: the admin contact shown to guests, the email server used to
 * send league email, plus a list of admin-only accounts (admins who are not
 * league players, so not on the Players list).
 */
class Settings extends BaseController
{
    public function edit(): string
    {
        return view('admin/settings', [
            'title'    => 'Settings',
            'settings' => (new SettingModel())->first() ?? [],
            'admins'   => $this->adminOnlyAccounts(),
            'errors'   => [],
        ]);
    }

    public function update()
    {
        $model   = new SettingModel();
        $current = $model->first();
        $post    = fn (string $name) => trim((string) $this->request->getPost($name)) ?: null;
        $data    = [
            'admin_contact_name'  => $post('admin_contact_name'),
            'admin_contact_email' => $post('admin_contact_email'),
            'smtp_host'           => $post('smtp_host'),
            'smtp_port'           => $post('smtp_port'),
            'smtp_crypto'         => $post('smtp_crypto'),
            'smtp_user'           => $post('smtp_user'),
            'mail_from_email'     => $post('mail_from_email'),
            'mail_from_name'      => $post('mail_from_name'),
        ];
        // A blank password box keeps the saved password, unless "clear" is ticked.
        $password = (string) $this->request->getPost('smtp_pass');
        if ($password !== '') {
            $data['smtp_pass_encrypted'] = Mailer::encryptPassword($password);
        } elseif ($this->request->getPost('smtp_pass_clear')) {
            $data['smtp_pass_encrypted'] = null;
        }

        $saved = $current ? $model->update($current['id'], $data) : $model->insert($data);
        if ($saved !== false) {
            return redirect()->to('admin/settings')->with('message', 'Settings saved.');
        }

        return view('admin/settings', [
            'title'    => 'Settings',
            'settings' => $data + ($current ?? []),
            'admins'   => $this->adminOnlyAccounts(),
            'errors'   => array_values($model->errors()),
        ]);
    }

    /**
     * Sends a short test message through the saved email server settings.
     */
    public function testEmail()
    {
        $back     = redirect()->to('admin/settings#email-server');
        $to       = trim((string) $this->request->getPost('test_to'));
        $settings = Mailer::settings();
        if (! Mailer::isConfigured($settings)) {
            return $back->with('error', 'Save the email server and From address first.');
        }
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $back->with('error', 'Enter the email address to send the test to.');
        }

        $email = Mailer::message(Mailer::email($settings), $settings);
        $email->setTo($to);
        $email->setSubject(APP_NAME . ' test email');
        $email->setMessage("This is a test email from " . APP_NAME . ". The email server settings are working.");
        if ($email->send(false)) {
            return $back->with('message', "Test email sent to {$to}.");
        }
        log_message('error', 'Test email failed: ' . $email->printDebugger(['headers']));

        return $back->with('error', 'The test email could not be sent. Check the server, port, security, username and password.');
    }

    private function adminOnlyAccounts(): array
    {
        return (new PlayerModel())->where('is_player', 0)->orderBy('login')->findAll();
    }
}
