<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomerSanctionValidationTest extends TestCase
{
    public function test_customer_create_and_update_reject_unchecked_sanction_confirmation(): void
    {
        $this->withoutMiddleware();

        foreach (['regular', 'corporate'] as $type) {
            foreach ([[], ['is_sanction_list_applied' => '0'], ['is_sanction_list_applied' => false]] as $confirmation) {
                // The route must enforce the requirement even if the submitted type is changed.
                $payload = array_merge(['customer_type' => 'temporary'], $confirmation);
                $this->postJson("/accounts/{$type}-customers", $payload)
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors('is_sanction_list_applied');

                $this->putJson("/accounts/{$type}-customers/1", $payload)
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors('is_sanction_list_applied');
            }
        }
    }
}
