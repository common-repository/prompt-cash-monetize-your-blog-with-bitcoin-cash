import {PromptCash} from "./PromptCash";
import {WebHelpers} from "./WebHelpers";


export class AbstractModule {
    protected plugin: PromptCash;
    protected webHelpers: WebHelpers;

    constructor(plugin: PromptCash, webHelpers: WebHelpers = null) {
        this.plugin = plugin;
        this.webHelpers = webHelpers ? webHelpers : this.plugin.getWebHelpers();
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################
}
