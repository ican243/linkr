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

        // 관리자 계정은 무차별 대입의 1순위 대상이라, 같은 IP / 같은 이메일 각각 5분에 5번까지만 시도 허용.
        // (IP만 보면 여러 IP로 한 계정을 노리는 공격을, 이메일만 보면 한 IP에서 여러 계정을 찍어보는 공격을 못 막음)
        $throttler = service('throttler');
        $ipKey     = 'admin_login_ip_' . md5($this->request->getIPAddress());
        $emailKey  = 'admin_login_email_' . md5((string) $email);

        if ($throttler->check($ipKey, 5, 300) === false || $throttler->check($emailKey, 5, 300) === false) {
            return view('admin/login', ['error' => '로그인 시도가 너무 많습니다. 잠시 후 다시 시도해주세요.']);
        }

        $model = new AdminModel();
        $admin = $model->where('email', $email)->first();

        if (! $admin || ! password_verify($password, $admin['password'])) {
            return view('admin/login', ['error' => '이메일 또는 비밀번호가 올바르지 않습니다.']);
        }

        // 로그인 직전에 세션 ID를 새로 발급 (세션 고정 방지). 회원 로그인 상태 등 기존 세션 값은 그대로 유지됨.
        session()->regenerate(true);

        session()->set([
            'admin_id'        => $admin['id'],
            'admin_email'     => $admin['email'],
            'isAdminLoggedIn' => true,
        ]);

        return redirect()->to('/admin/dashboard');
    }

    // 일반 로그아웃과 같은 이유로 POST + CSRF로만 받음
    public function logout()
    {
        // destroy()로 세션 전체를 지우지 않고 admin 관련 키만 제거한다.
        // 같은 브라우저에서 일반 회원(user) 로그인 상태를 유지한 채
        // 관리자만 로그아웃하는 경우를 깨뜨리지 않기 위해서.
        session()->remove(['admin_id', 'admin_email', 'isAdminLoggedIn']);

        return redirect()->to('/');
    }
}
