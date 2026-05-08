
/*
* 
* ===================================================================
* CLOUD FILE FOR SUPPORT BOARD ADMIN AREA
* ===================================================================
*
*/

(function ($) {
    var admin;
    var CLOUD_URL;
    var URL = document.location.href;

    var SBCloud = {
        shopify_products_box: false,
        shopify_products_box_ul: false,

        removeAdminID: function (ids) {
            let index = ids.indexOf(SB_ADMIN_SETTINGS.cloud.id);
            if (index != -1) {
                ids.splice(index, 1);
            }
            return ids;
        },

        creditsAlert: function (element, e) {
            let id = $(element).closest('[id]').attr('id');
            if (SB_ADMIN_SETTINGS.credits <= 0 && ((id.includes('google') && admin.find('#google-sync-mode select').val() == 'auto') || (id.includes('open-ai') && admin.find('#open-ai-sync-mode select').val() == 'auto'))) {
                SBAdmin.genericPanel('credits-panel', 'Credits required', '<p>' + sb_('To use the {R} feature in automatic sync mode, credits are required. If you don\'t want to buy credits, switch to manual sync mode and use your own API key.').replace('{R}', '<b>' + $(element).prev().html() + '</b>') + '</p>', [['Buy credits', 'plus']]);
                SBAdmin.settings.input.reset(element);
                e.preventDefault();
                return true;
            }
            return false;
        },

        creditsAlertQuota: function () {
            let docs = admin.find('.sb-docs').attr('href');
            SBAdmin.infoBottom(sb_('You have used all of your credits. Add more credits {R}.').replace('{R}', '<a href="account?tab=membership#credits">' + sb_('here') + '</a>') + (docs ? '<a href="' + docs + '#cloud-credits" target="_blank" class="sb-icon-link"><i class="sb-icon-help"></i></a>' : ''), 'error');
        },

        shopify: {

            conversationPanel: function () {
                let code = '';
                let shopify_id = SBF.activeUser().getExtra('shopify_id');
                if (!this.panel) {
                    this.panel = admin.find('.sb-panel-shopify');
                }
                if ((shopify_id || (SB_ADMIN_SETTINGS.shopify_shop && SBF.activeUser().getExtra('current_url') && SBF.activeUser().getExtra('current_url').value.includes(SB_ADMIN_SETTINGS.shopify_shop))) && !SBAdmin.loading(this.panel)) {
                    SBF.ajax({
                        function: 'shopify-get-conversation-details',
                        shopify_id: shopify_id ? shopify_id.value : false
                    }, (response) => {
                        code = `<i class="sb-icon-refresh"></i><h3>Shopify</h3><div><div class="sb-split"><div><div class="sb-title">${sb_('Number of orders')}</div><span>${response.orders_count} ${sb_('orders')}</span></div><div><div class="sb-title">${sb_('Total spend')}</div><span>${response.total}</span></div></div><div class="sb-title">${sb_('Cart')}</div><div class="sb-list-items sb-list-links sb-shopify-cart">`;
                        if (response.cart.items) {
                            for (var i = 0; i < response.cart.items.length; i++) {
                                let product = response.cart.items[i];
                                code += `<a href="${product.url}" target="_blank" data-id="${product.id}"><span>${product.quantity} x</span><span>${product.title}</span><span>${product.price}</span></a>`;
                            }
                        }
                        code += (response.cart.items && response.cart.items.length ? '' : '<p>' + sb_('The cart is currently empty.') + '</p>') + '</div>';
                        if (response.orders.length) {
                            code += `<div class="sb-title">${sb_('Orders')}</div><div class="sb-list-items sb-shopify-orders sb-accordion">`;
                            for (var i = 0; i < response.orders.length; i++) {
                                let order = response.orders[i];
                                let id = order.id;
                                let items = order.items;
                                code += `<div data-id="${id}"><span><span>#${id}</span><span>${order.price}</span><span>${SBF.beautifyTime(order.date, true)}</span><a href="${order.url}" target="_blank" class="sb-icon-next"></a></span><div>`;
                                for (var j = 0; j < items.length; j++) {
                                    code += `<a data-product-id="${items[j].id}"><span>${items[j].quantity} x</span> <span>${items[j].name}</span></a>`;
                                }
                                for (var j = 0; j < 2; j++) {
                                    let key = j == 0 ? 'shipping' : 'billing';
                                    if (order[key + '_address']) {
                                        code += `<div class="sb-title">${sb_((j == 0 ? 'Shipping' : 'Billing') + ' address')}</div><div class="sb-multiline">${order[key + '_address'].replace(/\n/g, '<br>')}</div>`;
                                    }
                                }
                                code += `<span data-status="${order.status}">${order.status}</span></div></div>`;
                            }
                            code += '</div>';
                        }
                        $(this.panel).html(code).sbLoading(false);
                        SBAdmin.collapse(this.panel, 160);
                    });

                } else {
                    $(this.panel).html(code);
                }
            }
        },
    }

    window.SBCloud = SBCloud;

    function manualSyncSettingsVisibility(setting_name, show = true) {
        let selectors = { google: '#google-client-id, #google-client-secret, #google-refresh-token', 'open-ai': '#open-ai-key', 'whatsapp-cloud': '#whatsapp-twilio-btn, #whatsapp-cloud-key', 'messenger': '#messenger-key, #messenger-path-btn' };
        let selectors_hide = { 'whatsapp-cloud': '#whatsapp-cloud-sync-btn, #whatsapp-cloud-reconnect-btn' };
        let items = admin.find(selectors[setting_name]);
        let items_hide = admin.find(selectors_hide[setting_name]);
        items.sbActive(show);
        items_hide.sbActive(!show);
        if (!show) {
            items.each(function () {
                $(this).find('input').val('');
            });
        }
    }

    function sb_(text) {
        return SB_TRANSLATIONS && text in SB_TRANSLATIONS ? SB_TRANSLATIONS[text] : text;
    }

    function meta_sync(whatsapp = true) {
        let config = whatsapp ? { config_id: SB_CLOUD_WHATSAPP.configuration_id, response_type: 'code', override_default_response_type: true, extras: { setup: {}, featureType: 'whatsapp_business_app_onboarding', sessionInfoVersion: '3' } } : { config_id: SB_CLOUD_MESSENGER.configuration_id, response_type: 'token' };
        FB.logout();
        FB.login(function (response) {
            response = response.authResponse ? response.authResponse : false;
            if (response && ((whatsapp && response.code) || (!whatsapp && response.accessToken))) {
                let button = admin.find(whatsapp ? '#whatsapp-cloud-sync-btn a' : '#messenger-sync-btn a');
                if (SBAdmin.loading(button)) {
                    return;
                }
                ajax(whatsapp ? 'whatsapp-sync' : 'messenger-sync', { access_token: response.accessToken, code: response.code }, (response) => {
                    button.sbLoading(false);
                    if (response && ((whatsapp && response.access_token) || (!whatsapp && Array.isArray(response) && response.length))) {
                        let repeater = admin.find(whatsapp ? '#whatsapp-cloud-numbers' : '#messenger-pages');
                        let repeater_items = repeater.find('.repeater-item');
                        let count_start = repeater_items.length;
                        let index = 0;
                        if (count_start == 1 && !repeater_items.eq(0).find('input').eq(0).val()) {
                            count_start = 0;
                        }
                        let count_end = (whatsapp ? response.phone_numbers.length : response.length) + count_start;
                        let existing_items = repeater.find(whatsapp ? '[data-id=whatsapp-cloud-numbers-phone-id]' : '[data-id=messenger-page-id]').map(function () { return $(this).val() }).get();
                        for (var i = count_start; i < count_end; i++) {
                            if (!existing_items.includes(whatsapp ? response.phone_numbers[index] : response[index].page_id)) {
                                if (i >= repeater_items.length) {
                                    repeater.find('.sb-repeater-add').click();
                                    repeater_items = repeater.find('.repeater-item');
                                }
                                let repeater_item = repeater_items.last();
                                if (whatsapp) {
                                    repeater_item.find('[data-id=whatsapp-cloud-numbers-phone-id]').val(response.phone_numbers[index]);
                                    repeater_item.find('[data-id=whatsapp-cloud-numbers-token]').val(response.access_token);
                                    repeater_item.find('[data-id=whatsapp-cloud-numbers-account-id]').val(response.waba_id);
                                } else {
                                    repeater_item.find('[data-id=messenger-page-name]').val(response[index].name);
                                    repeater_item.find('[data-id=messenger-page-id]').val(response[index].page_id);
                                    repeater_item.find('[data-id=messenger-page-token]').val(response[index].access_token);
                                    repeater_item.find('[data-id=messenger-instagram-id]').val(response[index].instagram);
                                }
                            }
                            index++;
                        }
                        SBAdmin.settings.save();
                        SBAdmin.infoBottom('Synchronization completed.');
                    } else {
                        console.error(response);
                        if (response.error && response.error.error_user_msg) {
                            SBAdmin.infoBottom(response.error.error_user_msg, 'error');
                        }
                    }
                });
            }
        }, config);
    }

    function ajax(function_name, data = {}, onSuccess = false) {
        $.extend(data, { function: function_name });
        $.ajax({
            method: 'POST',
            url: 'account/ajax.php',
            data: data
        }).done((response) => {
            if (onSuccess) {
                onSuccess(response === false ? false : JSON.parse(response));
            }
        });
    }

    function scrollPagination(area, check = false, offset = 0) {
        if (check) return $(area).scrollTop() + $(area).innerHeight() >= ($(area)[0].scrollHeight - 1);
        $(area).scrollTop($(area)[0].scrollHeight - offset);
    }

    $(document).ready(function () {
        admin = $('.sb-admin');
        CLOUD_URL = SB_URL.substring(0, SB_URL.substring(0, SB_URL.length - 2).lastIndexOf('/'));

        // Disable apps for free plan
        if (DISABLE_APPS == 'true' && (!SB_CLOUD_MEMBERSHIP || SB_CLOUD_MEMBERSHIP == '0' || SB_CLOUD_MEMBERSHIP == 'free')) {
            admin.find('#tab-messenger,#tab-whatsapp,#tab-twitter,#tab-telegram,#tab-wechat,#tab-viber,#tab-line').hide();
            admin.find('[data-app="messenger"],[data-app="whatsapp"],[data-app="twitter"],[data-app="telegram"],[data-app="wechat"],[data-app="viber"],[data-app="line"],[data-app="zalo"]').addClass('sb-disabled');
        }

        // Shopify
        if (SB_ADMIN_SETTINGS.shopify_shop) {
            admin.find('.sb-btn-saved-replies').after(`<div class="sb-btn-shopify" data-sb-tooltip="${sb_('Add Shopify product')}"></div>`);
            admin.find('.sb-editor').append(`<div class="sb-popup sb-shopify-products"><div class="sb-header"><div class="sb-select"><p data-value="">${sb_('All')}</p><ul class="sb-scroll-area"></ul></div><div class="sb-search-btn"><i class="sb-icon sb-icon-search"></i><input type="text" placeholder="${sb_('Search ...')}" /></div></div><div class="sb-shopify-products-list sb-list-thumbs sb-scroll-area"><ul class="sb-loading"></ul></div><i class="sb-icon-close sb-popup-close"></i></div>`);
            SBCloud.shopify_products_box = admin.find('.sb-shopify-products');
            SBCloud.shopify_products_box_ul = SBCloud.shopify_products_box.find(' > div > ul');
        }

        $(document).on('SBSettingsLoaded', function (e, settings) {

            // Credits and sync mode
            let settings_check = [['google', 'client-id'], ['open-ai', 'key'], ['whatsapp-cloud', 'key']];
            for (var i = 0; i < settings_check.length; i++) {
                let key = settings_check[i][0];
                if (settings[key] && ((settings[key][0][key + '-sync-mode'] && settings[key][0][key + '-sync-mode'][0] == 'manual') || settings[key][0][key + '-' + settings_check[i][1]][0])) {
                    manualSyncSettingsVisibility(key);
                    admin.find('#' + key + '-sync-mode select').val('manual');
                }
            }
            for (var key in SB_AUTO_SYNC) {
                if (!SB_AUTO_SYNC[key]) {
                    let element = admin.find('#' + key + '-sync-mode');
                    element.find('select').val('manual');
                    element.addClass('sb-hide');
                    manualSyncSettingsVisibility(key, true);
                }
            }
        });

        // Credits and sync mode
        $(admin).on('change', '#google-sync-mode select, #open-ai-sync-mode select, #whatsapp-cloud-sync-mode select', function () {
            manualSyncSettingsVisibility($(this).parent().attr('id').replace('-sync-mode', ''), $(this).val() == 'manual');
            SBAdmin.infoBottom('Save changes to apply new sync mode.', 'info');
        });

        $(admin).on('click', '#open-ai-active input, #open-ai-spelling-correction input, #open-ai-rewrite input, #open-ai-speech-recognition input, #sb-train-chatbot, #dialogflow-sync-btn .sb-btn, #google-multilingual input, #google-multilingual-translation input, #google-translation input, #google-language-detection input', function (e) {
            if (SBCloud.creditsAlert(this, e)) {
                return false;
            }
        });

        $(admin).on('change', '#open-ai-mode select', function () {
            if ($(this).val() == 'assistant') {
                admin.find('#open-ai-sync-mode select').val('manual');
                admin.find('#open-ai-key').sbActive(true);
            }
        });

        $(admin).on('change', '#open-ai-sync-mode select', function () {
            if ($(this).val() == 'auto') {
                let select = admin.find('#open-ai-mode select');
                if (select.val() == 'assistant') {
                    select.val('');
                }
                admin.find('#open-ai-assistant-id').sbActive(false);
            }
        });

        if (SB_ADMIN_SETTINGS.credits_required) {
            SBCloud.creditsAlertQuota();
        }

        // WhatsApp and Messenger
        let is_meta_sdk_loaded = false;
        $(admin).on('click', '#whatsapp-cloud-sync-btn .sb-btn, #whatsapp-cloud-reconnect-btn .sb-btn, #messenger-sync-btn a', function (e) {
            let id = $(this).parent().attr('id');
            let is_whatsapp = id != 'messenger-sync-btn';
            let reconnect = id == 'whatsapp-cloud-reconnect-btn' ? { scope: 'whatsapp_business_messaging, whatsapp_business_management, business_management' } : false;
            if (is_meta_sdk_loaded) {
                if (reconnect) {
                    FB.login(() => { }, reconnect);
                } else {
                    meta_sync(is_whatsapp);
                }
            } else {
                window.fbAsyncInit = function () {
                    FB.init(is_whatsapp ? { appId: SB_CLOUD_WHATSAPP.app_id, autoLogAppEvents: true, xfbml: true, version: 'v24.0' } : { appId: SB_CLOUD_MESSENGER.app_id, cookie: true, xfbml: true, version: 'v18.0' });
                };
                $.getScript('https://connect.facebook.net/en_US/sdk.js', () => {
                    is_meta_sdk_loaded = true;
                    if (reconnect) {
                        FB.login(() => { }, reconnect);
                    } else {
                        meta_sync(is_whatsapp);
                    }
                });
            }
            e.preventDefault()
            return false;
        });

        // Slack
        $(admin).on('click', '#slack-button a', function (e) {
            document.location.href = 'https://slack.com/oauth/v2/authorize?client_id=' + SB_ADMIN_SETTINGS.slack_client_id + '&install_redirect=update-to-granular-scopes&scope=channels:history,channels:join,channels:manage,channels:read,chat:write,chat:write.customize,chat:write.public,dnd:read,files:read,files:write,incoming-webhook,team:read,users.profile:read,users:read,users:read.email&user_scope=channels:write';
            e.preventDefault()
            return false;
        });

        if (URL.includes('code=') && URL.includes('slack-sync')) {
            let button = admin.find('#slack-button a');
            SBAdmin.loading(button);
            window.history.replaceState({}, document.title, CLOUD_URL);
            ajax('slack-sync', {
                code: new URLSearchParams(URL).get('code')
            }, (response) => {
                button.sbLoading(false);
                if (response && response.workspace) {
                    admin.find('#slack-workspace input').val(response.workspace);
                    admin.find('#slack-token input').val(response.bot_access_token);
                    admin.find('#slack-token-user input').val(response.user_access_token);
                    admin.find('#slack-channel input').val(response.channel_id);
                    SBAdmin.settings.save();
                    SBAdmin.infoBottom('Synchronization completed.');
                } else {
                    console.log(response);
                }
            });
        }

        if (URL.includes('bot-access-token')) {
            let url = new URLSearchParams(URL);
            window.history.replaceState({}, document.title, CLOUD_URL);
            admin.find('#slack-workspace input').val(url.get('workspace'));
            admin.find('#slack-token input').val(url.get('bot-access-token'));
            admin.find('#slack-token-user input').val(url.get('user-access-token'));
            admin.find('#slack-channel input').val(url.get('channel-id'));
            SBAdmin.settings.save();
            SBAdmin.infoBottom('Synchronization completed.');
        }

        // Onboarding
        if ((URL.includes('board.support') || URL.includes('support-board')) && URL.includes('welcome')) {
            admin.find('#sb-settings').click();
            let items = [
                ['Need help?', 'Not sure what to do? Contact us for help. Log in with your existing email and password. We reply in a few hours.', [], '', 'Contact us', 'https://board.support/docs/support'],
                [(SB_ADMIN_SETTINGS.shopify_shop ? 'Shopify ' : '') + 'Chatbot', 'Activate the ' + (SB_ADMIN_SETTINGS.shopify_shop ? 'Shopify' : 'OpenAI') + ' chatbot and Human Takeover to transfer the chat to an agent when needed.', ['0p2YWQtsglg'], '#optimal-configuration-ai', 'Activate'],
                ['Notifications', 'Activate Push and Email notifications to receive alerts for incoming messages. On iPhone, the mobile app is required.', ['t-qzDPG88Xg', 'enb291Aai5Q'], '#notifications', 'Activate'],
                ['Try out the chat', 'Try out the chat and send a test message to test notifications and the chatbot functionalities.', ['mxjRevd_8bw'], '#widget-hidden', 'Try now', 'https://chat.cloud.board.support/' + SB_ADMIN_SETTINGS.cloud.chat_id],
                ['Mobile app', 'The admin area is a PWA that can be installed on iPhones, Android, and mobile devices.', ['IhoAlXFywFY'], '#pwa', 'Read more', 'https://board.support/docs#pwa?cloud']
            ];
            if (SB_ADMIN_SETTINGS.shopify_shop) {
                items.push(['Shopify docs', 'Check the Shopify documentation to learn about the main features and how to enable them.', [], '#shopify', 'Read more', 'https://board.support/docs#shopify?cloud']);
            }
            let code = '<div>';
            let checks = ['push-notifications-active', 'open-ai-active'];
            for (var i = 0; i < items.length; i++) {
                let id = `id="onboarding-${items[i][3].replace('#', '')}"`;
                let video = '';
                for (var j = 0; j < items[i][2].length; j++) {
                    video += `<a href="https://www.youtube.com/watch?v=${items[i][2][j]}" target="_blank"><img src="account/media/play-video.svg"></a>`;
                }
                code += `<div class="sb-setting"><div><h2>${items[i][0]} ${video}<a href="https://board.support/docs/${items[i][3]}" target="_blank"><i class="sb-icon-help"></i></a></h2><p>${items[i][1]}</p></div><div>${items[i][5] ? `<a ${id} href="${items[i][5]}" target="_blank" class="sb-btn">${items[i][4]}</a>` : `<div ${id} class="sb-btn">${items[i][4]}</div>`}</div></div>`;
            }
            setTimeout(() => {
                SBAdmin.genericPanel('onboarding', `Welcome ${admin.find('> .sb-header > .sb-admin-nav-right .sb-account .sb-name').html()} 👋`, '<p>Support Board is a powerful tool with many options. Let\'s start by activating the basic functionalities below. Don\'t hesitate to reach out to us if you have any questions.</p>' + code + '</div>', [], '', true);
                for (var i = 0; i < checks.length; i++) {
                    if (admin.find('#' + checks[i] + ' input').prop('checked')) {
                        $('.sb-onboarding-box [id].sb-btn').eq(i).sbActive(true);
                    }
                }
                if (admin.find('.sb-admin-list .sb-scroll-area > ul li').length) {
                    $('#onboarding-widget-hidden').sbActive(true);
                }
            }, 1000);
        }

        $(admin).on('click', '#onboarding-notifications', function () {
            if ($(this).sbActive() || SBAdmin.loading(this)) {
                return
            }
            let ids = ['notify-agent-email', 'notify-user-email', 'push-notifications-active'];
            for (var i = 0; i < ids.length; i++) {
                admin.find('#' + ids[i] + ' input').prop('checked', true);
            }
            if (typeof OneSignal != 'undefined') {
                OneSignal.Slidedown.promptPush({ force: true });
            } else {
                SBF.serviceWorker.initPushNotifications();
            }
            $(document).on('SBPushNotificationSubscription', (e, response) => {
                $(this).sbLoading(false);
                if (response.optedIn) {
                    $(this).sbActive(true);
                }
            });
            SBAdmin.settings.save();
        });

        $(admin).on('click', '#onboarding-optimal-configuration-ai', function () {
            admin.find('#open-ai-active input').prop('checked', true);
            admin.find('#dialogflow-human-takeover-active input').prop('checked', true);
            admin.find('#dialogflow-human-takeover-message textarea').val('I\'m a chatbot. Do you want to get in touch with one of our agents?');
            admin.find('#dialogflow-human-takeover-message-confirmation textarea').val('Alright! We will get in touch soon!');
            admin.find('#dialogflow-human-takeover-confirm input').val('Yes');
            admin.find('#dialogflow-human-takeover-cancel input').val('Cancel');
            SBAdmin.settings.save();
            $(this).sbActive(true);
        });

        $(admin).on('click', '#onboarding-widget-hidden', function () {
            if ($(this).sbActive() || SBAdmin.loading(this)) {
                return
            }
            $(document).on('SBAdminNewConversation', () => {
                $(this).sbLoading(false);
                $(this).sbActive(true);
            });
        });

        $(admin).on('click', '#onboarding-pwa', function () {
            $(this).sbActive(true);
        });

        // Shopify
        $(admin).on('click', '[data-product-id]:not([href])', function () {
            SBF.ajax({
                function: 'shopify-get-product-link',
                product_id: $(this).attr('data-product-id')
            }, (response) => {
                $(this).attr('href', response).attr('target', '_blank');
                window.open(response, '_blank');
            });
        });

        $(admin).on('click', '.sb-panel-shopify > i', function () {
            SBCloud.shopify.conversationPanel();
        });

        $(admin).on('click', '.sb-btn-shopify', function () {
            if (SBCloud.shopify_products_box_ul.sbLoading() || (SB_ADMIN_SETTINGS.languages && SBF.activeUser() && SB_ADMIN_SETTINGS.languages.includes(activeUser().language) && SBF.activeUser().language != SBAdmin.apps.itemsPanel.panel_language)) {
                SBAdmin.apps.itemsPanel.populate('shopify');
            }
            SBCloud.shopify_products_box.find('.sb-search-btn').sbActive(true).find('input').get(0).focus();
            SBCloud.shopify_products_box.sbTogglePopup(this);
        });

        $(SBCloud.shopify_products_box).find('.sb-shopify-products-list').on('scroll', function () {
            if (scrollPagination(this, true)) {
                SBAdmin.apps.itemsPanel.pagination(this, 'shopify');
            }
        });

        $(SBCloud.shopify_products_box).on('click', '.sb-select li', function () {
            SBAdmin.apps.itemsPanel.filter(this, 'shopify');
        });

        $(SBCloud.shopify_products_box).on('input', '.sb-search-btn input', function () {
            SBAdmin.apps.itemsPanel.search(this, 'shopify');
        });

        $(SBCloud.shopify_products_box).on('click', '.sb-search-btn i', function () {
            SBF.searchClear(this, () => { SBAdmin.apps.itemsPanel.search($(this).next(), 'shopify') });
        });

        $(SBCloud.shopify_products_box).on('click', '.sb-shopify-products-list li', function () {
            SBChat.insertText(`{shopify product_id="${$(this).data('id')}"}`);
            SBF.deactivateAll();
            admin.removeClass('sb-popup-active');
        });

        // Miscellaneous
        $(admin).on('click', '.sb-admin-nav-right [data-value="account"], #sb-buy-credits', function () {
            document.location = CLOUD_URL + '/account?tab=membership' + ($(this).attr('id') == 'sb-buy-credits' ? '#credits' : '');
        });

        $(admin).on('click', '.sb-btn-app-disable', function () {
            if (SBAdmin.loading(this)) return;
            SBF.ajax({
                function: 'app-disable',
                app_name: $(this).closest('[data-app]').attr('data-app')
            }, (response) => {
                location.reload();
            });
        });
    });
}(jQuery));