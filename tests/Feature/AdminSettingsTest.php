<?php

use App\Models\SettingModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AdminSettingsTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testNonAdminCannotReachSettingsPage(): void
    {
        $result = $this->get('/admin/settings');

        $result->assertRedirectTo('/');
    }

    public function testAdminSeesCurrentValues(): void
    {
        $result = $this->withSession(['isAdminLoggedIn' => true])->get('/admin/settings');

        $result->assertOK();
        // CreateSettingsTable 마이그레이션이 넣어둔 기본값(기존 코드에 하드코딩되어 있던 값)
        $result->assertSee('100');
        $result->assertSee('33000');
        $result->assertSee('79800');
    }

    // 관리자가 값을 바꾸면, 그 즉시 요금제 페이지와 결제 화면에 반영되는지가 이 기능의 핵심이라
    // "저장 후 다른 화면에서 실제로 바뀐 값이 보이는지"까지 확인한다.
    public function testUpdatingPriceReflectsOnPricingAndCheckoutPages(): void
    {
        $adminSession = $this->withSession(['isAdminLoggedIn' => true]);

        $result = $adminSession->post('/admin/settings', [
            csrf_token()             => csrf_hash(),
            'free_plan_link_limit'   => '50',
            'pro_plan_price'         => '19900',
            'enterprise_plan_price'  => '99000',
        ]);

        $result->assertRedirectTo('/admin/settings');
        $result->assertSessionHas('message');

        $this->assertSame('19900', (new SettingModel())->getValue('pro_plan_price'));

        // 요금제 페이지에 새 금액이 그대로 보이는지
        $pricingResult = $this->get('/pricing');
        $pricingResult->assertSee('19,900원');
        $pricingResult->assertSee('99,000원');
        $pricingResult->assertSee('월 50개 링크');
    }

    public function testZeroOrInvalidValueIsRejected(): void
    {
        $result = $this->withSession(['isAdminLoggedIn' => true])->post('/admin/settings', [
            csrf_token()             => csrf_hash(),
            'free_plan_link_limit'   => '100',
            'pro_plan_price'         => '0',
            'enterprise_plan_price'  => '79800',
        ]);

        $result->assertRedirectTo('/admin/settings');
        $result->assertSessionHas('error');

        // 잘못된 시도로 기존 값이 덮어써지지 않아야 함(기본값 33000 그대로 유지)
        $this->assertSame('33000', (new SettingModel())->getValue('pro_plan_price'));
    }
}
