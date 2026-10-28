<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\StaffRequest;
use App\Models\Operation\Staff;
use App\Models\User;
use App\Services\Operation\StaffBadgeService;
use App\Services\Operation\StaffService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staff)
    {
        $this->authorizeResource(Staff::class, 'personal');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Operation/Staff/Index', $this->staff->indexData(
            $request->user(),
            $request->input('search', ''),
            $request->integer('church_id') ?: null,
            $request->integer('municipality_id') ?: null,
            $request->integer('community_id') ?: null,
            $request->integer('staff_type_id') ?: null,
        ));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Operation/Staff/Form', $this->staff->getFormData($request->user()));
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $this->staff->createStaff($request->validated());

        return redirect()->route('personal.index')
            ->with('success', 'Personal registrado correctamente.');
    }

    public function edit(Request $request, Staff $personal): Response
    {
        $personal = $personal->loadMissing(['staffType:id,name', 'church:id,name', 'community:id,name']);

        return Inertia::render('Operation/Staff/Form', $this->staff->getEditData($request->user(), $personal));
    }

    public function update(StaffRequest $request, Staff $personal): RedirectResponse
    {
        $this->staff->updateStaff($personal, $request->validated());

        return redirect()->route('personal.index')
            ->with('success', 'Personal actualizado correctamente.');
    }

    public function destroy(Staff $personal): RedirectResponse
    {
        $this->staff->deleteStaff($personal);

        return redirect()->route('personal.index')
            ->with('success', 'Personal eliminado correctamente.');
    }

    public function badgePdf(Request $request, Staff $personal, StaffBadgeService $badgeService)
    {
        abort_unless($request->user()?->can('personal.read'), 403);
        abort_unless($this->withinScope($request->user(), $personal), 403);

        $pdfContent = $badgeService->generateStaffBadgePdf($personal);
        $fileName = $this->buildBadgePdfFilename($personal);
        $isDownload = $request->query('download') === '1';
        $disposition = $isDownload ? 'attachment' : 'inline';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function withinScope(?User $user, Staff $staff): bool
    {
        if (! $user) {
            return false;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        return $scope->churchIds()->contains($staff->church_id)
            || $scope->communityIds()->contains($staff->community_id);
    }

    private function buildBadgePdfFilename(Staff $staff): string
    {
        $firstName = $this->firstWord((string) $staff->name, 'PERSONAL');
        $firstLastName = $this->firstWord((string) $staff->paterno, 'SIN_APELLIDO');
        $displayName = trim($firstName.' '.$firstLastName);
        $displayName = preg_replace('/[^\pL\pN\s\-]/u', '', $displayName) ?: 'PERSONAL SIN_APELLIDO';

        return 'Gafete de Personal '.$displayName.'.pdf';
    }

    private function firstWord(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        $parts = preg_split('/\s+/u', $value) ?: [];

        return $parts[0] ?? $fallback;
    }
}
