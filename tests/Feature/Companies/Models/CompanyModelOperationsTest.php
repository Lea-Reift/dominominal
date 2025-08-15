<?php

declare(strict_types=1);

use App\Enums\DocumentTypeEnum;
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Support\ValueObjects\Phone;

use function Pest\Laravel\{actingAs, assertDatabaseHas};

describe('Company Model Operations', function () {
    beforeEach(function () {
        /** @var User */
        $this->user = User::factory()->create();
        actingAs($this->user);
    });

    test('can create company with required fields', function () {
        $company = Company::create([
            'name' => 'Test Company S.A.',
            'address' => 'Calle 123, Ciudad Test',
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20123456789',
        ]);

        expect($company)->not->toBeNull();
        expect($company->name)->toBe('Test Company S.A.');
        expect($company->address)->toBe('Calle 123, Ciudad Test');
        expect($company->document_type)->toBe(DocumentTypeEnum::RNC);
        expect($company->document_number)->toBe('20123456789');
        expect($company->user_id)->toBe($this->user->id);

        assertDatabaseHas(Company::class, [
            'name' => 'Test Company S.A.',
            'address' => 'Calle 123, Ciudad Test',
            'document_type' => DocumentTypeEnum::RNC->value,
            'document_number' => '20123456789',
            'user_id' => $this->user->id,
        ]);
    });

    test('can create company with phones', function () {
        $phones = collect([
            new Phone('Oficina', '051123456789'),
            new Phone('Celular', '051987654321'),
        ]);

        $company = Company::create([
            'name' => 'Company with Phones',
            'address' => 'Test Address',
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20111222333',
            'phones' => $phones,
        ]);

        expect($company->phones)->toHaveCount(2);
        expect($company->phones->first())->toBeInstanceOf(Phone::class);
        expect($company->phones->first()->number)->toBe('051123456789');
        expect($company->phones->last()->type)->toBe('Celular');
    });

    test('automatically sets user_id when creating company', function () {
        $company = Company::create([
            'name' => 'Auto User Company',
            'address' => 'Test Address',
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '12345678',
        ]);

        expect($company->user_id)->toBe($this->user->id);
    });

    test('initializes empty phones collection when none provided', function () {
        $company = Company::create([
            'name' => 'No Phones Company',
            'address' => 'Test Address',
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20444555666',
        ]);

        expect($company->phones)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($company->phones)->toHaveCount(0);
    });

    test('can update company information', function () {
        $company = Company::factory()->create([
            'name' => 'Original Company',
            'address' => 'Original Address',
        ]);

        $company->update([
            'name' => 'Updated Company',
            'address' => 'Updated Address',
        ]);

        expect($company->name)->toBe('Updated Company');
        expect($company->address)->toBe('Updated Address');

        assertDatabaseHas(Company::class, [
            'id' => $company->id,
            'name' => 'Updated Company',
            'address' => 'Updated Address',
        ]);
    });

    test('can update company document information', function () {
        $company = Company::factory()->create([
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '87654321',
        ]);

        $company->update([
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20777888999',
        ]);

        expect($company->document_type)->toBe(DocumentTypeEnum::RNC);
        expect($company->document_number)->toBe('20777888999');
    });

    test('can update company phones', function () {
        $originalPhones = collect([new Phone('Original', '051111111111')]);
        $updatedPhones = collect([
            new Phone('Updated', '051222222222'),
            new Phone('New Phone', '051333333333'),
        ]);

        $company = Company::factory()->create(['phones' => $originalPhones]);

        $company->update(['phones' => $updatedPhones]);

        expect($company->phones)->toHaveCount(2);
        expect($company->phones->first()->number)->toBe('051222222222');
        expect($company->phones->last()->type)->toBe('New Phone');
    });

    test('can delete company', function () {
        $company = Company::factory()->create();
        $companyId = $company->id;

        $company->delete();

        expect(Company::find($companyId))->toBeNull();
    });

    test('maintains document type consistency', function () {
        $company = Company::factory()->create([
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20123456789',
        ]);

        expect($company->document_type)->toBe(DocumentTypeEnum::RNC);
        expect($company->document_number)->toHaveLength(11);
    });

    test('can handle different document types', function () {
        $dniCompany = Company::factory()->create([
            'document_type' => DocumentTypeEnum::IDENTIFICATION,
            'document_number' => '12345678',
        ]);

        $rucCompany = Company::factory()->create([
            'document_type' => DocumentTypeEnum::RNC,
            'document_number' => '20123456789',
        ]);

        expect($dniCompany->document_type)->toBe(DocumentTypeEnum::IDENTIFICATION);
        expect($rucCompany->document_type)->toBe(DocumentTypeEnum::RNC);
        expect($dniCompany->document_number)->toHaveLength(8);
        expect($rucCompany->document_number)->toHaveLength(11);
    });
});
