<?php

namespace App\Controllers;

use App\Models\UserModel;

class CustomDomain extends BaseController
{
    public function form()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $model = new UserModel();
        $user  = $model->find(session()->get('user_id'));

        return view('custom_domain/form', ['user' => $user]);
    }

    public function save()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $domain = trim((string) $this->request->getPost('custom_domain'));
        $model  = new UserModel();
        $user   = $model->find(session()->get('user_id'));

        if ($user['plan'] === 'free') {
            return redirect()->to('/custom-domain')->with('error', '커스텀 도메인은 프로 요금제부터 사용할 수 있습니다.');
        }

        if ($domain === '') {
            return redirect()->to('/custom-domain')->with('error', '도메인을 입력해주세요.');
        }

        // 형식이 아예 도메인이 아닌 문자열("asdf" 등)이 저장되는 것을 막는다.
        if (! preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain)) {
            return redirect()->to('/custom-domain')->with('error', '올바른 도메인 형식이 아닙니다. (예: mybrand.com)');
        }

        // 이미 다른 회원이 등록해둔 도메인이면 거부 (DB의 UNIQUE 제약과 별개로,
        // 사용자에게 이유를 알려주는 에러 메시지를 먼저 보여주기 위한 앱단 체크)
        $existing = $model->where('custom_domain', $domain)->first();
        if ($existing && $existing['id'] !== session()->get('user_id')) {
            return redirect()->to('/custom-domain')->with('error', '이미 다른 계정에서 등록한 도메인입니다.');
        }

        // 도메인을 새로 등록/변경하면 검증 상태는 항상 초기화(미검증)하고, TXT 검증용 코드도
        // 새로 발급한다(이 도메인의 DNS를 실제로 관리할 수 있는 사람만 인증을 통과하게 하기 위함).
        $model->update(session()->get('user_id'), [
            'custom_domain'              => $domain,
            'custom_domain_verified'     => false,
            'custom_domain_verify_code'  => bin2hex(random_bytes(12)),
        ]);

        return redirect()->to('/custom-domain')->with('message', '도메인이 저장되었습니다. DNS 연결을 확인해주세요.');
    }

    public function verify()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $model = new UserModel();
        $user  = $model->find(session()->get('user_id'));

        if (empty($user['custom_domain'])) {
            return redirect()->to('/custom-domain')->with('error', '먼저 도메인을 등록해주세요.');
        }

        if (empty($user['custom_domain_verify_code'])) {
            // 예전에(TXT 검증 도입 전에) 등록된 도메인이라 코드가 없는 경우를 위한 안전장치.
            $model->update($user['id'], ['custom_domain_verify_code' => bin2hex(random_bytes(12))]);

            return redirect()->to('/custom-domain')->with('error', '인증 코드가 새로 발급되었습니다. TXT 레코드를 설정한 뒤 다시 시도해주세요.');
        }

        $result = $this->checkTxtVerification($user['custom_domain'], $user['custom_domain_verify_code']);

        if ($result['matched']) {
            $model->update($user['id'], ['custom_domain_verified' => true]);

            return redirect()->to('/custom-domain')->with('message', 'TXT 레코드가 확인되어 인증 완료되었습니다.');
        }

        // 왜 실패했는지 알 수 있게, 지금 그 도메인에서 실제로 조회되는 TXT 레코드를 같이 보여준다.
        $detail = empty($result['found'])
            ? ' (현재 이 도메인에서는 TXT 레코드가 조회되지 않았습니다.)'
            : ' (현재 조회된 TXT 레코드: ' . implode(', ', $result['found']) . ')';

        return redirect()->to('/custom-domain')->with('error', '아직 TXT 레코드가 확인되지 않았습니다. 설정 후 반영까지 시간이 걸릴 수 있습니다.' . $detail);
    }

    public function remove()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        (new UserModel())->update(session()->get('user_id'), [
            'custom_domain'             => null,
            'custom_domain_verified'    => false,
            'custom_domain_verify_code' => null,
        ]);

        return redirect()->to('/custom-domain')->with('message', '커스텀 도메인 연결이 해제되었습니다.');
    }

    // 입력한 도메인의 TXT 레코드에 "linkr-verify=코드"가 실제로 들어있는지 확인해서
    // 소유권을 검증한다. (A 레코드가 우리 서버를 가리키는지는 여기서 검사하지 않음 —
    // 그건 "이 도메인이 실제로 이 소유자 것"이라는 증명과는 별개로, 트래픽이 실제로
    // 우리 서버까지 오려면 필요한 라우팅 설정일 뿐이라 인증 통과 조건에는 안 넣음.
    // A 레코드가 잘못돼있으면 인증은 되어도 실제 접속은 안 될 뿐이고, 그건 화면 안내로 알림.)
    // 실패했을 때 원인을 화면에 보여줄 수 있도록, 통과 여부(matched)뿐 아니라
    // 실제로 조회된 TXT 레코드 목록(found)도 같이 반환한다.
    private function checkTxtVerification(string $domain, string $code): array
    {
        $expected = 'linkr-verify=' . $code;
        $records  = @dns_get_record($domain, DNS_TXT);

        $found   = [];
        $matched = false;

        if (! empty($records)) {
            foreach ($records as $record) {
                if (! isset($record['txt'])) {
                    continue;
                }

                $found[] = $record['txt'];

                if ($record['txt'] === $expected) {
                    $matched = true;
                }
            }
        }

        return ['matched' => $matched, 'found' => $found];
    }
}
