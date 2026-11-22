<?php

use App\Services\RequestLifecycle;
use Tests\Support\FeatureTestCase;

/**
 * Ratings are per delivered request: each participant rates the other once
 * per errand, and every errand adds to the person's average.
 *
 * @internal
 */
final class RatingTest extends FeatureTestCase
{
    private function deliveredErrand(int $requester, int $runner): int
    {
        $requestId = $this->createRequest($requester);
        (new RequestLifecycle())->accept($requestId, $runner);
        $this->db->table('requests')->where('id', $requestId)->update(['status' => 'delivered']);
        $this->db->table('accepted_requests')->where('request_id', $requestId)->update(['status' => 'delivered']);

        return $requestId;
    }

    public function testEachDeliveryCanBeRatedOnceAndTheButtonDisappears(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser(['first_name' => 'Jose']);
        $requestId = $this->deliveredErrand($requester, $runner);

        $before = $this->getAs($requester, 'request-details/' . $requestId);
        $before->assertSee('Rate runner');
        $before->assertSee('id="rateDialog"', null);

        $this->postAs($requester, 'request/' . $requestId . '/rate', ['rating' => '4', 'feedback' => 'Careful with the eggs']);
        $this->assertSame('Thanks! Your rating was added.', $this->flash('success'));

        $after = $this->getAs($requester, 'request-details/' . $requestId);
        $after->assertDontSee('Rate runner');
        $after->assertDontSee('id="rateDialog"', null);
        $after->assertSee('You rated Jose');
        $after->assertSee('Careful with the eggs');

        // A second rating for the same delivery is refused.
        $this->postAs($requester, 'request/' . $requestId . '/rate', ['rating' => '1']);
        $this->assertSame('You already rated this delivery.', $this->flash('error'));
        $this->assertSame(1, $this->countRows('user_ratings'));
        $this->assertSame('4', (string) $this->db->table('user_ratings')->get()->getRow()->rating);
    }

    public function testRatingsStackAcrossDeliveries(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();

        $this->postAs($requester, 'request/' . $this->deliveredErrand($requester, $runner) . '/rate', ['rating' => '5']);
        $this->postAs($requester, 'request/' . $this->deliveredErrand($requester, $runner) . '/rate', ['rating' => '4']);

        $this->assertSame(2, $this->countRows('user_ratings', ['rated_user_id' => $runner]));
        $this->getAs($runner, 'profile')->assertSee('4.5 from 2 ratings');
    }

    public function testRunnerRatesTheRequesterIndependently(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->deliveredErrand($requester, $runner);

        $this->postAs($requester, 'request/' . $requestId . '/rate', ['rating' => '5']);
        $this->getAs($runner, 'request-details/' . $requestId)->assertSee('Rate requester');

        $this->postAs($runner, 'request/' . $requestId . '/rate', ['rating' => '3']);

        $this->assertSame(1, $this->countRows('user_ratings', ['rated_user_id' => $requester, 'rater_id' => $runner]));
        $this->assertSame(1, $this->countRows('user_ratings', ['rated_user_id' => $runner, 'rater_id' => $requester]));
    }

    public function testOutsidersAndBadValuesCannotRate(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $requestId = $this->deliveredErrand($requester, $runner);

        $this->postAs($this->createUser(), 'request/' . $requestId . '/rate', ['rating' => '5']);
        $this->assertStringContainsString('Only the requester and the runner', (string) $this->flash('error'));

        $this->postAs($requester, 'request/' . $requestId . '/rate', ['rating' => '9']);
        $this->assertStringContainsString('1 to 5', (string) $this->flash('error'));

        $this->assertSame(0, $this->countRows('user_ratings'));
    }

    public function testProfilePageNoLongerHasARatingForm(): void
    {
        $viewer = $this->createUser();
        $other  = $this->createUser();

        $page = $this->getAs($viewer, 'users/' . $other);

        $page->assertSee('Ratings come from completed errands');
        $page->assertDontSee('/rate"', null);
    }
}
