<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PlayerModel;
use App\Models\SettingModel;

/**
 * Site settings: the admin contact shown to guests, plus a list of admin-only
 * accounts (admins who are not league players, so not on the Players list).
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
        $data    = [
            'admin_contact_name'  => trim((string) $this->request->getPost('admin_contact_name')) ?: null,
            'admin_contact_email' => trim((string) $this->request->getPost('admin_contact_email')) ?: null,
        ];

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

    private function adminOnlyAccounts(): array
    {
        return (new PlayerModel())->where('is_player', 0)->orderBy('login')->findAll();
    }
}
