<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;

class Auth extends BaseController
{
    public function loginForm()
    {
        return view('admin/login');
    }

    public function login()
    {
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $model = new AdminModel();
        $admin = $model->where('email', $email)->first();

        if (! $admin || ! password_verify($password, $admin['password'])) {
            return view('admin/login', ['error' => '이메일 또는 비밀번호가 올바르지 않습니다.']);
        }

        session()->set([
            'admin_id'        => $admin['id'],
            'admin_email'     => $admin['email'],
            'isAdminLoggedIn' => true,
        ]);

        return redirect()->to('/admin/dashboard');
    }

    public function logout()
    {
        // destroy()로 세션 전체를 지우지 않고 admin 관련 키만 제거한다.
        // 같은 브라우저에서 일반 회원(user) 로그인 상태를 유지한 채
        // 관리자만 로그아웃하는 경우를 깨뜨리지 않기 위해서.
        session()->remove(['admin_id', 'admin_email', 'isAdminLoggedIn']);

        return redirect()->to('/');
    }
}
