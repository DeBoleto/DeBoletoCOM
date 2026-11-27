<?php

namespace Tests\Feature\Checkout;

use App\Modules\Checkout\Jobs\SendConversionEvent;
use App\Modules\Checkout\Tracking\MetaPixels;
use App\Modules\Checkout\Tracking\MetaTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetaPixelTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MetaPixels::class, new class extends MetaPixels
        {
            public function forEvent(int $eventId): array
            {
                return $eventId === 10
                    ? ['browser' => ['111111111', '222222222'], 'server' => ['111111111']]
                    : ['browser' => [], 'server' => []];
            }
        });

        $request = Request::create('/evento/demo?fbclid=ABC123', 'GET', [], ['_fbp' => 'fb.1.1700000000000.42'], [], [
            'REMOTE_ADDR' => '201.141.10.5',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Prueba',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
    }

    public function test_track_queues_the_browser_event_and_the_api_event_with_the_same_id(): void
    {
        Bus::fake();

        $id = app(MetaTracker::class)->track(10, 'ViewContent', ['content_name' => 'Demo']);

        $pending = app(MetaTracker::class)->pull();

        $this->assertCount(1, $pending);
        $this->assertSame(['111111111', '222222222'], $pending[0]['pixels']);
        $this->assertSame('ViewContent', $pending[0]['name']);
        $this->assertSame($id, $pending[0]['eventId']);
        $this->assertSame(['10'], $pending[0]['data']['content_ids']);
        $this->assertSame([], app(MetaTracker::class)->pull());

        Bus::assertDispatched(SendConversionEvent::class, function (SendConversionEvent $job) use ($id) {
            return $job->metaEventId === $id
                && $job->name === 'ViewContent'
                && $job->userData['client_ip_address'] === '201.141.10.5'
                && $job->userData['client_user_agent'] === 'Mozilla/5.0 Prueba'
                && $job->userData['fbp'] === 'fb.1.1700000000000.42'
                && str_starts_with($job->userData['fbc'], 'fb.1.')
                && str_ends_with($job->userData['fbc'], '.ABC123');
        });
    }

    public function test_events_without_pixels_are_ignored(): void
    {
        Bus::fake();

        $this->assertNull(app(MetaTracker::class)->track(99, 'ViewContent'));
        $this->assertSame([], app(MetaTracker::class)->pull());
        Bus::assertNotDispatched(SendConversionEvent::class);
    }

    public function test_initiate_checkout_is_sent_once_per_session(): void
    {
        Bus::fake();

        app(MetaTracker::class)->initiateCheckout(10);
        app(MetaTracker::class)->initiateCheckout(10);

        Bus::assertDispatchedTimes(SendConversionEvent::class, 1);
    }

    public function test_conversion_job_sends_event_id_user_data_and_custom_data(): void
    {
        Schema::create('eventos_pixel', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_evento');
            $table->string('pixel');
            $table->string('api');
        });
        DB::table('eventos_pixel')->insert([
            ['pk_evento' => 10, 'pixel' => '111111111', 'api' => 'token-a'],
            ['pk_evento' => 10, 'pixel' => '333333333', 'api' => ''],
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        (new SendConversionEvent(10, 'Purchase', 'https://deboleto.test/compra/ABC', ['client_ip_address' => '201.141.10.5'], ['currency' => 'MXN', 'value' => 250.0], 'purchase-ABC'))->handle();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $event = $request['data'][0];

            return str_contains($request->url(), '/111111111/events')
                && $request['access_token'] === 'token-a'
                && $event['event_id'] === 'purchase-ABC'
                && $event['user_data']['client_ip_address'] === '201.141.10.5'
                && $event['custom_data']['value'] === 250.0;
        });
    }
}
