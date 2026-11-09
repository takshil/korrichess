<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Account\AccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsentController extends Controller
{
    /** Consentements connus (RGPD #18, public majoritairement mineur). */
    private const KINDS = ['terms', 'parental_consent', 'data_retention', 'ocr_improvement'];

    public function __construct(private readonly AccountService $accounts)
    {
    }

    /** GET /api/v1/me/consents — état des consentements. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'consents' => $this->accounts->consents((int) $request->user()->id),
        ]);
    }

    /** PUT /api/v1/me/consents — accorder ou révoquer des consentements. */
    public function update(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $input = $request->input();

        foreach (is_array($input) ? $input : [] as $kind => $granted) {
            if (in_array((string) $kind, self::KINDS, true)) {
                $this->accounts->setConsent($userId, (string) $kind, (bool) $granted);
            }
        }

        return response()->json(['consents' => $this->accounts->consents($userId)]);
    }
}
