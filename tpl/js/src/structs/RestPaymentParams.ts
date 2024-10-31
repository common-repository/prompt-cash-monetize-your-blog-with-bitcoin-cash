import {PromptCashConfig} from "../PromptCash";
import {PaymentResponse} from "./PaymentResponse";

export type PaymentType = "WP" | "WC" | "RCP";

/**
 * Payment parameters we send to WP REST API to validate a payment.
 */
export class RestPaymentParams {
    public readonly amount: number;
    public readonly currency: string;
    public readonly postID: string;
    public readonly txID: string;

    protected type: PaymentType = "WP";

    /**
     * Creates new WC REST API params.
     * @param amount
     * @param currency
     * @param postID postID-buttonNr (nr 0 for WC orders)
     * @param txID
     */
    constructor(amount: number, currency: string, postID: string, txID: string) {
        this.amount = amount;
        this.currency = currency;
        this.postID = postID;
        this.txID = txID;
    }

    public static fromButton(config: PromptCashConfig, button: JQuery, data: PaymentResponse): RestPaymentParams {
        let params = new RestPaymentParams(parseFloat(button.attr("data-amount")),
            button.attr("data-currency"), button.attr("data-id"), data.tx_id);
        return params;
    }

    public static fromWoocommerceOrder(config: PromptCashConfig, data: PaymentResponse): RestPaymentParams {
        const wcConf = config.woocommerce;
        const orderID = `${wcConf.orderID}`;
        let params = new RestPaymentParams(wcConf.amount, wcConf.currency, orderID, data.tx_id);
        params.type = "WC";
        return params;
    }
}
