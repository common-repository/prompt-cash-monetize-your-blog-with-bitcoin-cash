import {PromptCashConfig} from "./PromptCash";

export class Gateway {
    protected lastPromptTxId: string = "";

    protected config: PromptCashConfig;

    constructor(config: PromptCashConfig) {
        this.config = config;
    }

    public generateRandomTxId(postID: number): string {
        const rand = this.getRandomString(4, false);
        const unixTimestamp = Math.floor(Date.now() / 1000);
        this.lastPromptTxId = `${postID}-${unixTimestamp}-${rand}`;
        return this.lastPromptTxId;
    }

    public getLastGeneratedTxId(): string {
        return this.lastPromptTxId;
    }

    public getPayFrameUrl(postID: number, amount: number, currency: string, type: string = "Post", description: string = ""): string {
        const promptTxId = this.generateRandomTxId(postID);
        currency = currency.toUpperCase(); // TODO undefined on tip button once. how?
        const desc = encodeURI(description.length !== 0 ? description : `Post ${postID} @ ${this.config.siteUrl}`.trim());
        const urlStr = this.config.frameUrl
            .replace("{promptTxId}", promptTxId)
            .replace("{amount}", amount.toFixed(8))
            .replace("{currency}", currency)
            .replace("{desc}", desc);
        return urlStr;
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################

    protected getRandomString(len: number, hex = false) {
        let chars = hex ? '1234567890ABCDEF' : '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
        let random = ''
        for (let i = 0; i < len; i++)
            random += chars.charAt(Math.floor(Math.random() * chars.length))
        return random
    }
}
