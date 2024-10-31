import {PromptCash} from "./src/PromptCash";

let promptCashPlugin = new PromptCash(window as any, jQuery);
(window as any).promptCashPlugin = promptCashPlugin;
