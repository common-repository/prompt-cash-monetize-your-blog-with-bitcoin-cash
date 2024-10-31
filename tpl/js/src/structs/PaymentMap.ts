import {PaymentResponse} from "./PaymentResponse";

export class PaymentMap extends Map<string, PaymentResponse> { // (prompt TX ID, pending payment)
    constructor() {
        super();
    }

    /**
     * Merge certain pre-defined properties from the existing payment onto newData and return it.
     * @param promptTxId
     * @param newData
     */
    public mergeWithExisting(promptTxId: string, newData: PaymentResponse): PaymentResponse {
        let payment = this.get(promptTxId);
        if (payment !== undefined) { // should always be defined in our current payment flow
            newData.is_restricted = payment.is_restricted;
        }
        this.set(promptTxId, newData);
        return newData;
    }
}
