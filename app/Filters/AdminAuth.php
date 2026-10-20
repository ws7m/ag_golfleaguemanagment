<?php

namespace App\Filters;

use App\Models\PlayerModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sends anyone who is not logged in as an admin to the admin login page.
 * Also logs out an admin whose record was deleted, deactivated or had its
 * admin access removed since they logged in.
 */
class AdminAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $adminId = session('admin_id');
        $admin   = $adminId ? (new PlayerModel())->find($adminId) : null;

        if ($admin === null || ! $admin['is_admin'] || ! $admin['active']) {
            if ($adminId) {
                session()->remove(['admin_id', 'admin_name']);
            }

            return redirect()->to('login')->with('error', 'Please log in as an admin.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
