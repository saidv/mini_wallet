<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferRequest;
use App\Http\Requests\ValidateReceiverRequest;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private TransferService $transferService,
        private UserRepositoryInterface $userRepository,
        private TransactionRepositoryInterface $transactionRepository
    ) {
    }

    /**
     * Validate receiver email before transfer.
     */
    public function validateReceiver(ValidateReceiverRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $sender = Auth::user();

        // Check if trying to send to self
        if ($email === $sender->email) {
            return response()->json([
                'status' => 'error',
                'data' => [
                    'valid' => false,
                    'message' => 'You cannot transfer money to yourself',
                ],
            ], 400);
        }

        $receiver = $this->userRepository->findByEmail($email);

        if (! $receiver) {
            return response()->json([
                'status' => 'error',
                'data' => [
                    'valid' => false,
                    'message' => 'No user found with this email address',
                ],
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'valid' => true,
                'user' => [
                    'id' => $receiver->id,
                    'name' => $receiver->name,
                    'email' => $receiver->email,
                ],
            ],
        ]);
    }

    /**
     * Transfer money to another user.
     */
    public function store(TransferRequest $request): JsonResponse
    {
        $sender = Auth::user();
        $receiverEmail = $request->input('receiver_email');
        $amount = $request->input('amount');

        $receiver = $this->userRepository->findByEmail($receiverEmail);

        // Get or generate idempotency key
        $idempotencyKey = $request->header('Idempotency-Key') ?? TransferService::generateIdempotencyKey(
            $sender->id,
            $receiver->id,
            $amount,
            now()->toIso8601String()
        );

        try {
            $transaction = $this->transferService->transfer(
                sender_id: $sender->id,
                receiver_id: $receiver->id,
                amount: $amount,
                idempotency_key: $idempotencyKey,
                metadata: [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            // Reload models to get fresh data
            $transaction->refresh();
            $sender->refresh();
            $receiver->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Transfer completed successfully',
                'data' => [
                    'uuid' => $transaction->uuid,
                    'amount' => $transaction->amount,
                    'commission' => $transaction->commission,
                    'total_debited' => $transaction->total_debited,
                    'sender_balance' => $sender->balance,
                    'receiver_balance' => $receiver->balance,
                    'created_at' => $transaction->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get transaction history for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $direction = $request->query('direction', 'all');
        $perPage = min($request->query('per_page', 20), 100);

        $transactions = $this->transactionRepository->getPaginatedForUser(
            $user->id,
            $perPage,
            $direction !== 'all' ? $direction : null
        );

        return response()->json([
            'data' => $transactions->items(),
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
        ]);
    }

    /**
     * Get a single transaction by UUID.
     */
    public function show(string $uuid): JsonResponse
    {
        $user = Auth::user();
        $transaction = $this->transactionRepository->findByUuid($uuid);

        if (! $transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found',
            ], 404);
        }

        // Verify user owns this transaction
        if ($transaction->sender_id !== $user->id && $transaction->receiver_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found',
            ], 404);
        }

        $isSender = $transaction->sender_id === $user->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'uuid' => $transaction->uuid,
                'direction' => $isSender ? 'out' : 'in',
                'sender' => $transaction->sender->only(['id', 'name', 'email']),
                'receiver' => $transaction->receiver->only(['id', 'name', 'email']),
                'amount' => $transaction->amount,
                'commission' => $transaction->commission,
                'total_debited' => $transaction->total_debited,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->toIso8601String(),
                'updated_at' => $transaction->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get transaction statistics for authenticated user.
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->transactionRepository->getStatsForUser($user->id);

        $stats['net_balance_change'] = $stats['total_received'] - $stats['total_sent'];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
