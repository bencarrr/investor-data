<?php

use App\Jobs\ProcessInvestorImport;
use App\Models\Import;
use App\Models\Investment;
use App\Models\Investor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

function csvUpload(string $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'investors.csv',
        "investor_id,name,age,investment_amount,investment_date\n".$rows,
    );
}

it('stores the upload and queues it, returning 202 with a status location', function () {
    Queue::fake();

    $response = $this->postJson(route('v1.imports.store'), ['file' => csvUpload("1,Ann Lee,30,100.00,01-02-2024\n")]);

    $import = Import::sole();
    $response->assertAccepted()
        ->assertHeader('Location', route('v1.imports.show', $import))
        ->assertJsonPath('data.id', $import->id)
        ->assertJsonPath('data.status', 'pending');
    Storage::assertExists($import->path);
    Queue::assertPushed(ProcessInvestorImport::class, fn (ProcessInvestorImport $job) => $job->import->is($import));
    $this->assertDatabaseCount('investors', 0);
});

it('imports every row of the sample file once the job has run', function () {
    $file = new UploadedFile(base_path('tests/Fixtures/investors.csv'), 'investors.csv', 'text/csv', test: true);

    $location = $this->postJson(route('v1.imports.store'), ['file' => $file])->headers->get('Location');

    $this->getJson($location)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_rows', 200)
        ->assertJsonPath('data.imported_rows', 200)
        ->assertJsonPath('data.failed_rows', 0)
        ->assertJsonPath('data.errors', []);
    expect(Investor::count())->toBe(200)
        ->and(Investment::count())->toBe(200);
    $this->assertDatabaseHas('investors', ['id' => 1001, 'name' => 'Daniel Nelson', 'age' => 28]);
    $this->assertDatabaseHas('investments', ['investor_id' => 1001, 'amount' => '328085.43', 'invested_on' => '2024-11-13']);
    Storage::assertMissing(Import::sole()->path);
});

it('does not duplicate data when the same file is imported twice', function () {
    $rows = "1,Ann Lee,30,100.00,01-02-2024\n2,Bob Ray,40,200.50,02-02-2024\n";

    $this->postJson(route('v1.imports.store'), ['file' => csvUpload($rows)])->assertAccepted();
    $this->postJson(route('v1.imports.store'), ['file' => csvUpload($rows)])->assertAccepted();

    expect(Investor::count())->toBe(2)
        ->and(Investment::count())->toBe(2);
});

it('stores one investment per investor per date, keeping the last value in the file', function () {
    $rows = "1,Ann Lee,30,100.00,01-02-2024\n1,Ann Lee,31,999.99,01-02-2024\n1,Ann Lee,31,50.00,02-02-2024\n";

    $this->postJson(route('v1.imports.store'), ['file' => csvUpload($rows)]);

    expect(Import::sole()->imported_rows)->toBe(3);
    $this->assertDatabaseCount('investments', 2);
    $this->assertDatabaseHas('investments', ['investor_id' => 1, 'invested_on' => '2024-02-01', 'amount' => '999.99']);
    $this->assertDatabaseHas('investors', ['id' => 1, 'age' => 31]);
});

it('keeps the last value when the same investor and date fall in different chunks', function () {
    $rows = "1,Ann Lee,30,100.00,01-01-2020\n";

    foreach (range(2, 1200) as $investorId) {
        $rows .= "{$investorId},Person {$investorId},40,10.00,01-01-2024\n";
    }

    $rows .= "1,Ann Lee,30,555.55,01-01-2020\n";

    $this->postJson(route('v1.imports.store'), ['file' => csvUpload($rows)]);

    expect(Import::sole()->imported_rows)->toBe(1201);
    $this->assertDatabaseCount('investors', 1200);
    $this->assertDatabaseHas('investments', ['investor_id' => 1, 'invested_on' => '2020-01-01', 'amount' => '555.55']);
});

it('skips invalid rows and reports them by line number', function () {
    $rows = "1,Ann Lee,30,100.00,01-02-2024\n2,Bob Ray,12,200.00,02-02-2024\n3,Cat Fox,40,300.00,2024-02-03\n4,Dan Ink,50,400.00\n";

    $location = $this->postJson(route('v1.imports.store'), ['file' => csvUpload($rows)])->headers->get('Location');

    $this->getJson($location)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_rows', 4)
        ->assertJsonPath('data.imported_rows', 1)
        ->assertJsonPath('data.failed_rows', 3)
        ->assertJsonPath('data.errors', [
            ['line' => 3, 'errors' => ['age' => ['The age field must be between 18 and 120.']]],
            ['line' => 4, 'errors' => ['investment_date' => ['The investment date field must match the format d-m-Y.']]],
            ['line' => 5, 'errors' => ['row' => ['Expected 5 columns, got 4.']]],
        ]);
    $this->assertDatabaseCount('investors', 1);
});

it('marks the import as failed with the reason when the job fails', function () {
    $import = Import::factory()->create();

    (new ProcessInvestorImport($import))->failed(new RuntimeException('Database went away'));

    $this->getJson(route('v1.imports.show', $import))
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'Database went away');
});

it('returns 404 for an unknown import', function () {
    $this->getJson(route('v1.imports.show', 999))->assertNotFound();
});

it('returns 422 when no file is uploaded', function () {
    $this->postJson(route('v1.imports.store'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'The file field is required.']);
});

it('returns 422 when the upload is not a CSV', function () {
    $this->postJson(route('v1.imports.store'), ['file' => UploadedFile::fake()->image('investors.png')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'The file field must be a file of type: csv.']);
});

it('returns 422 when the header row does not match', function () {
    $file = UploadedFile::fake()->createWithContent('investors.csv', "id,full_name,age,amount,date\n1,Ann Lee,30,100.00,01-02-2024\n");

    $this->postJson(route('v1.imports.store'), ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'The file header must be: investor_id,name,age,investment_amount,investment_date.']);

    $this->assertDatabaseCount('imports', 0);
});
