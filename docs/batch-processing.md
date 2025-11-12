# Batch Processing

SynapseToonProcessLLMBatchJob is the queue-native orchestrator for bundling dozens of prompts into a single LLM request. It compresses payloads with `SynapseToonEncoder`, routes them via `SynapseToonLLMRouter`, and records real-time ROI through `SynapseToonMetrics` so that high-volume workloads stay efficient, observable, and cheap.

## Pipeline Overview

1. Collect prompt fragments and metadata at dispatch time.
2. `SynapseToonEncoder` builds a minified TOON payload with optional dictionary compression.
3. `SynapseToonLLMRouter` selects the target model/connection based on complexity and the provided context.
4. The router response is annotated with throughput metrics and persisted by `SynapseToonMetrics`.

The job ships with sensible defaults (`"\t"` delimiter, automatic batch size detection) and is safe to run inside batches, chains, or failure callbacks.

## Dispatching the Job

```php
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;

SynapseToonProcessLLMBatchJob::dispatch($requests, [
	'queue' => 'llm-batch',
	'connection' => 'openai.primary',
	'delimiter' => "\t",
	'dictionary' => [
		'user_id' => 'u',
		'prompt' => 'p',
		'metadata' => 'm',
	],
	'estimated_json_tokens' => 12500,
]);
```

### Available Options

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `queue` | string\|null | `null` | Queue name forwarded to Laravel's `onQueue`. |
| `connection` | string\|null | `null` | Named router connection (for multi-provider workloads). |
| `delimiter` | string | `"\t"` | Token inserted between prompts prior to encoding. |
| `dictionary` | array | `[]` | Key remapping dictionary passed to `SynapseToonEncoder`. |
| `estimated_json_tokens` | int\|null | `null` | Raw-token estimate used when metrics calculates savings. |
| `savings_percent` | float\|null | `null` | Custom ROI value recorded alongside automatic stats. |

## Building an Accumulator

```php
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;

class SynapseToonBatchAccumulator
{
	public function __construct(
		private string $cacheKey = 'synapsetoon:batch:pending',
		private int $threshold = 50,
		private int $ttlSeconds = 60,
	) {}

	public function add(array $payload): void
	{
		$batch = Cache::get($this->cacheKey, []);
		$batch[] = $payload;

		Cache::put($this->cacheKey, $batch, $this->ttlSeconds);

		if (count($batch) >= $this->threshold) {
			$this->flush();
		}
	}

	public function flush(): void
	{
		$batch = Cache::pull($this->cacheKey, []);

		if ($batch === []) {
			return;
		}

		SynapseToonProcessLLMBatchJob::dispatch($batch, [
			'queue' => 'llm-batch',
			'estimated_json_tokens' => array_sum(array_column($batch, 'tokens')),
		]);
	}
}
```

## Parallel Fan-Out

```php
use Illuminate\Support\Collection;
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;

class SynapseToonParallelBatchDispatcher
{
	public function __construct(private int $chunkSize = 64) {}

	/**
	 * @param Collection<int, array<string, mixed>> $items
	 */
	public function dispatch(Collection $items): void
	{
		$items->chunk($this->chunkSize)
			->each(function ($chunk): void {
				SynapseToonProcessLLMBatchJob::dispatch($chunk->values()->all(), [
					'queue' => 'llm-batch',
					'connection' => 'openai.bulk',
					'delimiter' => "\u{001E}", // record separator
				]);
			});
	}
}
```

## Scheduled Flushes

```php
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;

class SynapseToonBatchFlushCommand extends Command
{
	protected $signature = 'synapsetoon:batch:flush';

	public function handle(): int
	{
		$batch = Cache::pull('synapsetoon:batch:pending', []);

		if ($batch === []) {
			$this->info('No pending batches.');
			return self::SUCCESS;
		}

		$count = count($batch);

		SynapseToonProcessLLMBatchJob::dispatch($batch, [
			'queue' => 'llm-batch',
		]);

		$this->info("Dispatched {$count} requests.");
		return self::SUCCESS;
	}
}
```

Register the command inside `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
	$schedule->command('synapsetoon:batch:flush')->everyMinute();
}
```

## Observability Hooks

SynapseToonProcessLLMBatchJob automatically records:

- `batch_size`
- encoded token count through `SynapseToonEncoder::estimatedTokens`
- optional `savings_percent`
- router status plus selected target model

You can enrich these metrics or drive dashboards with a tiny helper:

```php
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;

class SynapseToonBatchMetricsRecorder
{
	public function __construct(private SynapseToonMetrics $metrics) {}

	public function record(string $batchId, int $count, float $seconds): void
	{
		$this->metrics->record([
			'endpoint' => 'batch',
			'batch_id' => $batchId,
			'batch_size' => $count,
			'duration_seconds' => $seconds,
			'throughput' => $count / $seconds,
		]);
	}
}
```

## Real-World Example

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;

class SynapseToonNewsletterBatchJob implements ShouldQueue
{
	public function handle(): void
	{
		$recipients = User::query()
			->where('newsletter_enabled', true)
			->select(['id', 'email', 'name'])
			->chunk(100);

		foreach ($recipients as $chunk) {
			$requests = $chunk->map(fn ($user) => [
				'prompt' => $this->buildPrompt($user),
				'tokens' => 380,
				'metadata' => ['user_id' => $user->id],
			])->all();

			SynapseToonProcessLLMBatchJob::dispatch($requests, [
				'queue' => 'llm-batch',
				'connection' => 'openai.newsletter',
			]);
		}
	}

	private function buildPrompt($user): string
	{
		return view('llm.newsletter', compact('user'))->render();
	}
}
```

## Testing Patterns

```php
use Illuminate\Support\Facades\Bus;
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;

class SynapseToonBatchAccumulatorTest extends TestCase
{
	public function test_it_dispatches_when_threshold_reached(): void
	{
		Bus::fake();

		$accumulator = app(SynapseToonBatchAccumulator::class);

		foreach (range(1, 50) as $i) {
			$accumulator->add(['prompt' => "hello {$i}", 'tokens' => 120]);
		}

		Bus::assertDispatched(SynapseToonProcessLLMBatchJob::class);
	}
}
```

## Performance Heuristics

- 50–120 items per batch hit the sweet spot between compression gains and router latency.
- Supply dictionaries for repetitive payload keys to unlock an extra 8–12% token savings before compression kicks in.
- Pick `"\u{001E}"` or other non-printable delimiters when merging documents so semantic boundaries survive compression.
- Monitor `savings_percent` and retune thresholds whenever the value dips below 20%.

## Next Steps

- [Model Router](model-router.md) – Route workloads with SynapseToonLLMRouter.
- [Performance Tuning](performance-tuning.md) – Squeeze even more throughput from batch jobs.
- [Metrics & Analytics](metrics-analytics.md) – Visualize SynapseToonMetrics output.
- [Technical Reference](technical-reference.md) – Deep-dive into every SynapseToon component.
