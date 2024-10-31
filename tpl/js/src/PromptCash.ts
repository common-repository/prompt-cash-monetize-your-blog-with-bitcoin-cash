import {Tooltips} from "./admin/Tooltips";
import {WebHelpers, WebHelpersConfig} from "./WebHelpers";
import {Payment} from "./Payment";
import {AdminControls} from "./admin/AdminControls";
import {BrowserWindow} from "./types";

export interface PromptCashConfig extends WebHelpersConfig {
    cookieLifeDays: number;
    cookiePath: string;
    siteUrl: string;
    show_search_engines: boolean;
    gatewayOrigin: string;
    frameUrl: string;

    // WP Post
    postID: number;
    title: string;

    woocommerce?: {
        amount: number;
        currency: string;
        orderID: number;
        paymentPage: boolean;
    }

    // localizations
    tr: {
        order: string;
        post: string;
    }
}

export interface PromptCashApiRes {
    error: boolean;
    errorMsg: string;
    data: any[];
}

export class PromptCash {
    protected static readonly CONSENT_COOKIE_NAME = "prc-ck";
    protected static readonly CONFIRM_COOKIES_MSG = "#ct-cookieMsg";
    protected static readonly CONFIRM_COOKIES_BTN = "#ct-confirmCookies";
    // TODO separate entryPoints + classes for admin + public code? but tooltips and other admin stuff can be used publicly too (and is quite small)

    public readonly window: BrowserWindow;
    public readonly $: JQueryStatic;

    protected config: PromptCashConfig;
    protected webHelpers: WebHelpers;
    protected adminControls: AdminControls;
    protected tooltips: Tooltips;
    protected payment: Payment;

    constructor(window: BrowserWindow, $: JQueryStatic) {
        this.window = window;
        this.$ = $;
        this.config = this.window['promptCashCfg'] || {};
        this.config.consentCookieName = PromptCash.CONSENT_COOKIE_NAME;
        this.config.confirmCookiesMsg = PromptCash.CONFIRM_COOKIES_MSG;
        this.config.confirmCookiesBtn = PromptCash.CONFIRM_COOKIES_BTN;

        this.webHelpers = new WebHelpers(this.window, this.$, this.config);
        this.tooltips = new Tooltips(this, this.webHelpers);
        this.adminControls = new AdminControls(this);
        this.payment = new Payment(this);
        this.$(this.window.document).ready(($) => {
            this.adminControls.init();
            this.webHelpers.checkCookieConsent();
        });
    }

    public getConfig() {
        return this.config;
    }

    public getTooltips() {
        return this.tooltips;
    }

    public getWebHelpers() {
        return this.webHelpers;
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################
}
