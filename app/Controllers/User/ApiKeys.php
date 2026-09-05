<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\ApiKeyModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ApiKeys extends BaseController
{
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $user = (new UserModel())->find(session()->get('user_id'));

        $model = (new ApiKeyModel())
            ->where('user_id', session()->get('user_id'))
            ->orderBy('created_at', 'DESC');

        $keys = $model->paginate(10);

        // 요금제 페이지(pricing.php)에서 "API 액세스는 엔터프라이즈 전용"이라고 안내하는 것과
        // 실제 발급 가능 여부를 맞추기 위한 플래그. 뷰에서 발급 폼 노출 여부를 결정함.
        return view('user/api_keys', [
            'keys'       => $keys,
            'pager'      => $model->pager,
            'canUseApi'  => $user['plan'] === 'enterprise',
        ]);
    }

    public function create()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $user = (new UserModel())->find(session()->get('user_id'));

        if ($user['plan'] !== 'enterprise') {
            return redirect()->to('/api-keys')->with('error', 'API 키 발급은 엔터프라이즈 요금제 전용 기능입니다. 요금제를 업그레이드해주세요.');
        }

        $label = trim((string) $this->request->getPost('label'));

        if ($label === '') {
            return redirect()->to('/api-keys')->with('error', '키 별명을 입력해주세요.');
        }

        // king_ 접두사로 API 키임을 한눈에 알아볼 수 있게 하고, 뒤에 48자리 랜덤 hex를 붙인다.
        $rawKey = 'king_' . bin2hex(random_bytes(24));

        (new ApiKeyModel())->insert([
            'user_id'   => session()->get('user_id'),
            'label'     => $label,
            'key_hash'  => hash('sha256', $rawKey),
            'key_last4' => substr($rawKey, -4),
        ]);

        // 원본 키는 DB에 저장하지 않으므로, 발급 직후 이 리다이렉트 한 번에만 화면에 노출된다.
        return redirect()->to('/api-keys')->with('newKey', $rawKey);
    }

    public function delete(int $id)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $model = new ApiKeyModel();
        $key   = $model->find($id);

        if (! $key || (int) $key['user_id'] !== (int) session()->get('user_id')) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);

        return redirect()->to('/api-keys')->with('message', 'API 키가 삭제(폐기)되었습니다.');
    }
}
