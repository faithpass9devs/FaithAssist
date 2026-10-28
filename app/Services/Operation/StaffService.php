<?php

namespace App\Services\Operation;

use App\Models\Operation\Staff;
use App\Models\User;
use App\Repositories\Operation\StaffRepository;
use App\Services\StaffCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StaffService
{
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly StaffCodeGenerator $codeGenerator,
    ) {}

    public function indexData(
        User $user,
        string $search,
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $staffTypeId = null,
    ): array {
        $rows = $this->staff->paginateWithFilters(
            $user,
            $search,
            $churchId,
            $municipalityId,
            $communityId,
            $staffTypeId,
        );

        $options = $this->staff->getFilterOptions($user);

        return [
            'staff' => $rows->through(fn (Staff $row) => $this->staff->serializeStaff($row)),
            'search' => $search,
            'filters' => [
                'church_id' => $churchId,
                'municipality_id' => $municipalityId,
                'community_id' => $communityId,
                'staff_type_id' => $staffTypeId,
            ],
            'churches' => $options['churches'],
            'municipalities' => $options['municipalities'],
            'communities' => $options['communities'],
            'staffTypes' => $options['staffTypes'],
        ];
    }

    public function getFormData(User $user): array
    {
        $options = $this->staff->getFormOptions($user);

        return [
            'staff' => null,
            'churches' => $options['churches'],
            'municipalities' => $options['municipalities'],
            'communities' => $options['communities'],
            'staffTypes' => $options['staffTypes'],
            'countryCodes' => $this->staff->getLadaOptions(),
            'defaultCountryCode' => $this->staff->getDefaultLada(),
        ];
    }

    public function getEditData(User $user, Staff $staff): array
    {
        $options = $this->staff->getFormOptions($user);

        return [
            'staff' => $this->staff->serializeStaff($staff),
            'churches' => $options['churches'],
            'municipalities' => $options['municipalities'],
            'communities' => $options['communities'],
            'staffTypes' => $options['staffTypes'],
            'countryCodes' => $this->staff->getLadaOptions(),
            'defaultCountryCode' => $this->staff->getDefaultLada(),
        ];
    }

    public function createStaff(array $data): Staff
    {
        $data = $this->normalizePhonePayload($data);

        return DB::transaction(function () use ($data): Staff {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $data['code'] = $this->codeGenerator->generate($data);

                try {
                    return $this->staff->create($data);
                } catch (QueryException $e) {
                    $sqlState = $e->errorInfo[0] ?? null;
                    $message = $e->getMessage();

                    $isUniqueViolation = in_array($sqlState, ['23000', '23505'], true)
                        && (str_contains($message, 'staff.code') || str_contains($message, 'staff_code_unique'));

                    if (! $isUniqueViolation) {
                        throw $e;
                    }
                }
            }

            throw new \RuntimeException('Unable to generate a unique staff code.');
        });
    }

    public function updateStaff(Staff $staff, array $data): Staff
    {
        $data = $this->normalizePhonePayload($data);

        return DB::transaction(fn (): Staff => $this->staff->update($staff, Arr::only($data, [
            'staff_type_id',
            'church_id',
            'community_id',
            'name',
            'paterno',
            'materno',
            'phone_lada',
            'phone',
            'birthdate',
        ])));
    }

    public function deleteStaff(Staff $staff): void
    {
        $this->staff->delete($staff);
    }

    /**
     * Evita guardar la lada junto con el número local.
     * Si el usuario pega número completo (+código + local), se separa automáticamente.
     */
    private function normalizePhonePayload(array $data): array
    {
        $lada = preg_replace('/\D/', '', (string) ($data['phone_lada'] ?? '')) ?: null;
        $phone = preg_replace('/\D/', '', (string) ($data['phone'] ?? '')) ?: null;

        if (
            $lada && $phone
            && str_starts_with(ltrim((string) ($data['phone'] ?? '')), '+')
            && str_starts_with($phone, $lada)
            && strlen($phone) > strlen($lada)
        ) {
            $phone = substr($phone, strlen($lada)) ?: null;
        }

        $data['phone_lada'] = $lada;
        $data['phone'] = $phone;

        return $data;
    }
}
