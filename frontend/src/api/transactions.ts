import apiClient from './client';

export interface Transaction {
  uuid: string;
  sender_id: number;
  receiver_id: number;
  amount: number;
  commission: number;
  total_debited: number;
  status: 'completed' | 'failed' | 'pending';
  idempotency_key: string;
  sender_balance_before: number;
  sender_balance_after: number | null;
  receiver_balance_before: number;
  receiver_balance_after: number | null;
  created_at: string;
  completed_at: string | null;
  sender?: {
    id: number;
    name: string;
    email: string;
  };
  receiver?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface TransactionsResponse {
  data: Transaction[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface TransactionStats {
  total_received: number;
  total_sent: number;
  total_commission: number;
  total_transactions: number;
  pending_transactions: number;
  failed_transactions: number;
  net_balance_change: number;
}

export interface StatsResponse {
  status: string;
  data: TransactionStats;
}

export interface TransferRequest {
  receiver_email: string;
  amount: number;
}

export interface TransferResponse {
  status: string;
  message: string;
  data: {
    uuid: string;
    amount: number;
    commission: number;
    total_debited: number;
    sender_balance: number;
    receiver_balance: number;
    created_at: string;
  };
}

export interface ValidateReceiverResponse {
  status: string;
  data: {
    valid: boolean;
    user?: {
      id: number;
      name: string;
      email: string;
    };
    message?: string;
  };
}

export const transactionsApi = {
  /**
   * Get recent transactions (paginated)
   */
  async getTransactions(page: number = 1, perPage: number = 10): Promise<TransactionsResponse> {
    const response = await apiClient.get<TransactionsResponse>('/transactions', {
      params: { page, per_page: perPage },
    });
    return response.data;
  },

  /**
   * Get a specific transaction by UUID
   */
  async getTransaction(uuid: string): Promise<Transaction> {
    const response = await apiClient.get<Transaction>(`/transactions/${uuid}`);
    return response.data;
  },

  /**
   * Get transaction statistics for current user
   */
  async getStats(): Promise<TransactionStats> {
    const response = await apiClient.get<StatsResponse>('/transactions/stats');
    return response.data.data;
  },

  /**
   * Transfer money to another user
   */
  async transfer(data: TransferRequest, idempotencyKey?: string): Promise<TransferResponse> {
    const response = await apiClient.post<TransferResponse>('/transactions', data, {
      headers: idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {},
    });
    return response.data;
  },

  /**
   * Validate receiver email before transfer
   */
  async validateReceiver(email: string): Promise<ValidateReceiverResponse> {
    const response = await apiClient.post<ValidateReceiverResponse>(
      '/transactions/validate-receiver',
      { email }
    );
    return response.data;
  },
};
