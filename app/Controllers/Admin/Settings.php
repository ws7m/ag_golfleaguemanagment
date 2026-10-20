<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;

/**
 * Site settings: the shared player login and the admin contact shown to guests.
 */
class Settings extends BaseController
{
    public function edit(): string
    {
        return view('admin/settings', [
            'title'    => 'Settings',
            'settings' => (new SettingModel())->first() ?? [],
            'errors'   => [],
        ]);
    }

    public function update()
    {
        $model    = new SettingModel();
        $current  = $model->first();
        $password = (string) $this->request->getPost('shared_password');
        $data     = [
            'shared_login_id'     => trim((string) $this->request->getPost('shared_login_id')) ?: null,
            'admin_contact_name'  => trim((string) $this->request->getPost('admin_contact_name')) ?: null,
            'admin_contact_email' => trim((string) $this->request->getPost('admin_contact_email')) ?: null,
        ];

        $errors = [];
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'The shared password must be at least 8 characters.';
        }
        if ($password !== '' && $data['shared_login_id'] === null) {
            $errors[] = 'Enter a shared login ID to go with the password.';
        }
        if ($password !== '') {
            $data['shared_password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($errors === []) {
            $saved = $current ? $model->update($current['id'], $data) : $model->insert($data);
            if ($saved !== false) {
                return redirect()->to('admin/settings')->with('message', 'Settings saved.');
            }
            $errors = array_values($model->errors());
        }

        return view('admin/settings', [
            'title'    => 'Settings',
            'settings' => $data + ($current ?? []),
            'errors'   => $errors,
        ]);
    }
}
