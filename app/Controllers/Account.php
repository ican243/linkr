<?php

namespace App\Controllers;

use App\Models\UserModel;

class Account extends BaseController
{
    public function form()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $model = new UserModel();
        $user  = $model->find(session()->get('user_id'));

        return view('account/form', ['user' => $user]);
    }

    public function update()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $model  = new UserModel();
        $userId = session()->get('user_id');
        $user   = $model->find($userId);

        $name           = trim((string) $this->request->getPost('name'));
        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword     = (string) $this->request->getPost('new_password');

        $data = ['name' => $name];

        // 새 비밀번호를 입력했을 때만 변경 절차를 거침. 입력 안 했으면 이름만 바뀜.
        if ($newPassword !== '') {
            if (! password_verify($currentPassword, $user['password'])) {
                return view('account/form', ['user' => $user, 'error' => '현재 비밀번호가 올바르지 않습니다.']);
            }

            if (strlen($newPassword) < 8) {
                return view('account/form', ['user' => $user, 'error' => '새 비밀번호는 8자 이상이어야 합니다.']);
            }

            $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $model->update($userId, $data);

        return redirect()->to('/account')->with('message', '계정 정보가 수정되었습니다.');
    }
}
