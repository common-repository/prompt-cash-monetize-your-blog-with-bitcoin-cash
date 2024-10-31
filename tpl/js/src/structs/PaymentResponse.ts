
export type PaymentStatus = "PENDING" | "PAID" | "CANCELLED" | "EXPIRED" | "HOLD" | "REFUNDED";

export class PaymentResponse {
    public readonly id: number;
    public readonly tx_id: string; // the value the merchant supplied
    public readonly token: string;
    // TODO add more props
    public readonly status: PaymentStatus;
    public readonly paid_amount_crypto: number;
    public readonly remaining_amount_crypto: number;
    public readonly qr_url: string;
    public readonly payment_link: string;
    public readonly confirmations: number;

    // added by TS client
    public is_restricted: boolean = false;

    constructor() {
    }

    public static fromJson(json: any): PaymentResponse {
        return Object.assign(new PaymentResponse(), json); // copy properties
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################

}
