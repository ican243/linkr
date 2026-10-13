<?php

namespace App\Controllers;

use App\Libraries\AccountDeletion;
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

    // 회원 탈퇴. 실수로 누르는 걸 막으려고 확인 칸에 "탈퇴"를 직접 입력해야 진행됨.
    // (소셜 로그인 회원은 비밀번호가 없어서 비밀번호 대신 이 방식으로 확인)
    public function delete()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $userId = (int) session()->get('user_id');
        $user   = (new UserModel())->find($userId);

        if (trim((string) $this->request->getPost('confirm')) !== '탈퇴') {
            return view('account/form', ['user' => $user, 'deleteError' => '확인 칸에 "탈퇴"를 정확히 입력해 주세요.']);
        }

        AccountDeletion::delete($userId);

        // destroy()를 하면 아래 안내 메시지(flash)까지 사라져서, 로그인 정보만 지우고 세션 번호를 새로 발급
        session()->remove(['user_id', 'user_email', 'isLoggedIn', 'is_demo']);
        session()->regenerate(true);

        return redirect()->to('/')->with('message', '회원 탈퇴가 완료되었습니다. 그동안 이용해 주셔서 감사합니다.');
    }
}
