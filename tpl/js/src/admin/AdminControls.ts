import {AbstractModule} from "../AbstractModule";
import {PromptCash} from "../PromptCash";

export class AdminControls extends AbstractModule {
    constructor(plugin: PromptCash) {
        super(plugin);
    }

    public init() {
        if (this.plugin.$("body").attr("class").indexOf("promptcash") === -1)
            return; // not our plugin settings page

        this.plugin.getTooltips().initToolTips();
        this.plugin.$(this.plugin.window.document).ready(($) => {

        });
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################

}
