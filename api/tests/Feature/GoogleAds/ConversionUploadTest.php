<?php

namespace Tests\Feature\GoogleAds;

use App\Modules\GoogleAds\Models\GoogleAdsAccount;
use App\Modules\GoogleAds\Models\LeadConversionUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithCrm;
use Tests\TestCase;

class ConversionUploadTest extends TestCase
{
    use InteractsWithCrm;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->tenant = $this->createTenantWithRoles();
    }

    private function fakeGoogle(array $uploadResponse = ['results' => [['gclid' => 'Cj0-abc']]]): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3599]),
            'googleads.googleapis.com/*/googleAds:search' => Http::response(['results' => [
                ['conversionAction' => ['id' => '111', 'name' => 'Lead qualificado', 'status' => 'ENABLED']],
                ['conversionAction' => ['id' => '222', 'name' => 'Venda', 'status' => 'ENABLED']],
            ]]),
            'googleads.googleapis.com/*:uploadClickConversions' => Http::response($uploadResponse),
        ]);
    }

    private function configure(): void
    {
        $this->actingAsAdmin();
        $this->putJson('/api/integrations/google-ads', [
            'customer_id' => '123-456-7890', 'developer_token' => 'dev', 'client_id' => 'cid.apps.googleusercontent.com',
            'client_secret' => 'secret', 'refresh_token' => '1//refresh', 'qualified_action_id' => '111', 'won_action_id' => '222',
        ])->assertOk()->assertJsonPath('data.is_configured', true)->assertJsonPath('data.has_refresh_token', true)->assertJsonMissingPath('data.refresh_token');
    }

    public function test_activation_validates_conversion_actions(): void
    {
        $this->fakeGoogle();
        $this->configure();
        $this->putJson('/api/integrations/google-ads', ['won_action_id' => '999'])->assertOk();

        $this->postJson('/api/integrations/google-ads/activate')->assertUnprocessable()->assertJsonValidationErrors('won_action_id');

        $this->putJson('/api/integrations/google-ads', ['won_action_id' => '222'])->assertOk();
        $this->postJson('/api/integrations/google-ads/activate')->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_qualified_and_won_leads_are_uploaded_automatically(): void
    {
        $this->fakeGoogle();
        $lead = $this->siteLead(['gclid' => 'Cj0-abc']);
        $this->configure();
        $this->postJson('/api/integrations/google-ads/activate')->assertOk();

        $this->patchJson("/api/leads/{$lead->uuid}", ['status' => 'won', 'conversion_value' => 1500])->assertOk();

        $uploads = LeadConversionUpload::withoutTenancy()->get()->keyBy('kind');
        $this->assertSame('uploaded', $uploads['qualified']->status);
        $this->assertSame('uploaded', $uploads['won']->status);
        $this->assertEquals(1500, $uploads['won']->value);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'uploadClickConversions')) return false;
            $conversions = collect($request['conversions']);

            return str_contains($request->url(), '/v26/customers/1234567890:uploadClickConversions')
                && $request->header('developer-token')[0] === 'dev'
                && $request['partialFailure'] === true
                && $conversions->contains(fn ($c) => $c['conversionAction'] === 'customers/1234567890/conversionActions/222'
                    && $c['gclid'] === 'Cj0-abc' && $c['conversionValue'] == 1500 && $c['currencyCode'] === 'BRL'
                    && $c['consent']['adUserData'] === 'GRANTED' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $c['conversionDateTime']));
        });
    }

    public function test_partial_failure_keeps_retryable_conversion_pending(): void
    {
        $this->fakeGoogle(['results' => [[]], 'partialFailureError' => ['code' => 3, 'message' => 'erro', 'details' => [[
            'errors' => [[
                'errorCode' => ['conversionUploadError' => 'CLICK_NOT_FOUND'],
                'message' => 'O clique não foi encontrado.',
                'location' => ['fieldPathElements' => [['fieldName' => 'conversions', 'index' => 0]]],
            ]],
        ]]]]);
        $lead = $this->siteLead();
        $this->configure();
        $this->putJson('/api/integrations/google-ads', ['won_action_id' => null])->assertOk();
        GoogleAdsAccount::withoutTenancy()->update(['is_active' => true]);

        $this->patchJson("/api/leads/{$lead->uuid}", ['status' => 'qualified'])->assertOk();

        $upload = LeadConversionUpload::withoutTenancy()->firstOrFail();
        $this->assertSame('pending', $upload->status);
        $this->assertSame(1, $upload->attempts);
        $this->assertStringContainsString('CLICK_NOT_FOUND', $upload->error);
    }

    public function test_leads_without_click_id_are_ignored(): void
    {
        $this->fakeGoogle();
        $lead = $this->formLead();
        $this->configure();
        $this->postJson('/api/integrations/google-ads/activate')->assertOk();

        $this->patchJson("/api/leads/{$lead->uuid}", ['status' => 'qualified'])->assertOk();

        $this->assertSame(0, LeadConversionUpload::withoutTenancy()->count());
    }
}
