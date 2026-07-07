<?php

/**
 * Plugin Name: Muguerza Checkout Theme
 * Description: Rediseño de la página del carrito y checkout para Christus Muguerza.
 * Author: Acsyt
 * Author URI: http://acsyt.com
 * Developer: Acsyt
 * Text Domain: acsyt
 * Version: 0.0.2
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: muguerza-checkout
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) {
    exit;
}

define('MGC_PATH', plugin_dir_path(__FILE__));
define('MGC_URL',  plugin_dir_url(__FILE__));

if (!function_exists('alg_wc_ev_generate_user_code')) {
    /**
     * Compatibility shim for legacy email templates that still call the
     * Email Verification for WooCommerce helper directly.
     *
     * The original plugin may be disabled or removed, but older theme
     * overrides can still reference this function while rendering the
     * "customer new account" email during checkout.
     */
    function alg_wc_ev_generate_user_code($user)
    {
        if ($user instanceof WP_User) {
            $wp_user = $user;
        } elseif (is_numeric($user)) {
            $wp_user = get_user_by('id', (int) $user);
        } elseif (is_string($user) && is_email($user)) {
            $wp_user = get_user_by('email', $user);
        } else {
            $wp_user = false;
        }

        if (!($wp_user instanceof WP_User)) {
            return wp_generate_password(20, false, false);
        }

        return wp_hash(
            implode('|', [
                $wp_user->ID,
                $wp_user->user_email,
                $wp_user->user_registered,
            ])
        );
    }
}

function mgc_asset_version($relative_path)
{
    $full_path = MGC_PATH . ltrim($relative_path, '/');

    return file_exists($full_path) ? (string) filemtime($full_path) : '0.0.0';
}

function mgc_enqueue_scripts() {
    if (is_cart()) {
        wp_enqueue_style('mgc-cart', MGC_URL . 'assets/css/cart.css', [], mgc_asset_version('assets/css/cart.css'));
    }
    if (is_checkout()) {
        wp_enqueue_style('mgc-checkout', MGC_URL . 'assets/css/checkout.css', [], mgc_asset_version('assets/css/checkout.css'));
    }
    if (is_order_received_page()) {
        wp_enqueue_style('mgc-thankyou', MGC_URL . 'assets/css/thankyou.css', [], mgc_asset_version('assets/css/thankyou.css'));
    }
}
add_action('wp_enqueue_scripts', 'mgc_enqueue_scripts', 999);

// Botón "Continuar comprando" en el carrito
add_action('woocommerce_after_cart_totals', function () {
    echo '<a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="button wc-backward">'
        . esc_html__('Continuar comprando', 'tu-textdomain')
        . '</a>';
});

/**
 * Header “Finalizar compra” + steps en checkout
 */
add_action('woocommerce_before_checkout_form', 'cly_checkout_header_steps', 5);
function cly_checkout_header_steps()
{
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return;
    }
?>

    <div class="mg-checkout-header">
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="mg-back">← VOLVER AL CARRITO</a>

        <h1 class="mg-title">Finalizar compra</h1>

        <p class="mg-subtitle">
            Completa los siguientes pasos para finalizar tu compra
        </p>

        <div class="mg-steps">
            <div class="mg-step active" data-step="1">
                <span class="number">1</span>
                <span class="label">Información del paciente</span>
            </div>
            <div class="mg-line"></div>
            <div class="mg-step" data-step="2">
                <span class="number">2</span>
                <span class="label">Facturación</span>
            </div>
            <div class="mg-line"></div>
            <div class="mg-step" data-step="3">
                <span class="number">3</span>
                <span class="label">Método de pago</span>
            </div>
        </div>
    </div>

<?php
}

add_action('wp_footer', 'cly_checkout_steps_script', 30);
function cly_checkout_steps_script()
{
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return;
    }
?>
    <script>
        (function() {
            var steps = document.querySelectorAll('.mg-step');
            if (!steps.length) return;
            var form = document.querySelector('form.checkout.woocommerce-checkout');
            var sect1 = document.querySelector('#customer_details');
            var sect2 = document.querySelector('.woocommerce-additional-fields');
            var sect3 = document.querySelector('#payment');
            var current = 1;
            window.clyUserSelectedPayment = window.clyUserSelectedPayment || 0;

            function placeWideMessage() {
                var msg = document.querySelector('.mg-messaje-checkout');
                if (msg && form) {
                    form.appendChild(msg);
                }
            }

            function buildStep3UI() {
                var sect3 = document.querySelector('.woocommerce-checkout-payment, #payment');
                if (!sect3) return;

                var existingExtra = sect3.querySelector('.mg-extra-payments');
                if (existingExtra) existingExtra.remove();

                if (window.mg_extra_payments_html) {
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = window.mg_extra_payments_html.trim();
                    var extra = tempDiv.firstChild;
                    if (extra) {
                        var methodsList = sect3.querySelector('.wc_payment_methods');
                        var placeOrderDiv = sect3.querySelector('.form-row.place-order');
                        if (methodsList) {
                            sect3.insertBefore(extra, methodsList);
                        } else if (placeOrderDiv) {
                            sect3.insertBefore(extra, placeOrderDiv);
                        } else {
                            sect3.appendChild(extra);
                        }
                    }
                }
                enhancePaymentSelection();
                initCashbackActions();
                ensureConektaIframeVisible();
            }

            function ensureConektaIframeVisible() {
                var checked = document.querySelector('#payment .wc_payment_method input.input-radio:checked');
                if (!checked || (checked.value || '') !== 'conekta') return;

                var paymentBox = document.querySelector('#payment .payment_method_conekta .payment_box');
                var container = document.querySelector('#conektaIframeContainer');
                var wrapper = container ? container.firstElementChild : null;
                var iframe = container ? container.querySelector('iframe.zoid-visible, iframe[title=\"conekta_embedded_checkout_component_tokenizer\"]') : null;

                if (paymentBox) {
                    paymentBox.style.display = '';
                    paymentBox.style.visibility = 'visible';
                }

                if (!container) return;

                var needsHeightFix = false;
                if (container.offsetHeight < 40) needsHeightFix = true;
                if (wrapper && wrapper.offsetHeight < 40) needsHeightFix = true;
                if (iframe && iframe.offsetHeight === 0) needsHeightFix = true;

                if (!needsHeightFix) return;

                container.style.minHeight = '420px';
                container.style.display = 'block';
                container.style.visibility = 'visible';

                if (wrapper) {
                    wrapper.style.height = '420px';
                    wrapper.style.minHeight = '420px';
                    wrapper.style.display = 'block';
                    wrapper.style.position = 'relative';
                }

                if (iframe) {
                    iframe.style.height = '420px';
                    iframe.style.minHeight = '420px';
                    iframe.style.display = 'block';
                }

                window.dispatchEvent(new Event('resize'));
            }

            function showPayerInfoFields(show) {
                const paymentWrapper = document.querySelector('.mg-payment-wrapper');
                if (!paymentWrapper) return;

                // Create container for payer info in step 3 if it doesn't exist
                let payerContainer = paymentWrapper.querySelector('.mg-payer-info-container');
                if (!payerContainer) {
                    payerContainer = document.createElement('div');
                    payerContainer.className = 'mg-payer-info-container';
                    payerContainer.style.setProperty('display', 'flow-root')

                    const paymentWrapperTitle = document.createElement('h3');
                    paymentWrapperTitle.textContent = 'Método de pago';
                    paymentWrapperTitle.classList.add('mg-payment-wrapper-title');
                    
                    paymentWrapper.insertBefore(payerContainer, paymentWrapper.firstChild);
                    paymentWrapper.insertBefore(paymentWrapperTitle, paymentWrapper.firstChild);

                    const payerContainerTitle = document.createElement('h3');
                    payerContainerTitle.textContent = 'Información del pagador';
                    payerContainerTitle.classList.add('mg-payer-info-container-title');
                    payerContainer.appendChild(payerContainerTitle);
                }

                // Find payer info fields in their original location
                let payerFields = document.querySelectorAll('#customer_details .mg_payer_info')

                // If fields found in original location, move them to step 3
                if (payerFields.length) {
                    payerFields.forEach(function(field) {
                        payerContainer.appendChild(field)
                    })
                } else {
                    // Otherwise they're already in step 3
                    payerFields = paymentWrapper.querySelectorAll('.mg_payer_info');
                }  

                if (show) {
                    // Display all payer fields in step 3
                    payerFields.forEach(function(field) {
                        field.style.removeProperty('display');  // Remove any inline display style
                        field.style.setProperty('display', 'block', 'important')
                    })
                    initPayerInfoToggle();
                } else {
                    // Hide payer info fields everywhere
                    const payerFields = document.querySelectorAll('.mg_payer_info');
                    payerFields.forEach(function(field) {
                        field.style.setProperty('display', 'none', 'important');
                    });
                }
            }

            function initPayerInfoToggle() {
                var checkbox = document.querySelector('#payer_info_same_as_patient');
                if (!checkbox) return;

                function togglePayerFields() {
                    var checked = checkbox.checked;
                    var payerDetailFields = document.querySelectorAll('[id^="payer_info_"]:not(#payer_info_same_as_patient)');
                    
                    payerDetailFields.forEach(function(field) {
                        var wrapper = field.closest('.form-row') || field.parentElement;
                        if (wrapper) {
                            if (checked) {
                                wrapper.classList.remove('hidden');
                                wrapper.classList.add('display');
                            } else {
                                wrapper.classList.add('hidden');
                                wrapper.classList.remove('display');
                            }
                        }
                    });
                }

                // Remove any existing listeners by replacing the element
                if (!checkbox.hasAttribute('data-payer-toggle-bound')) {
                    checkbox.addEventListener('change', togglePayerFields);
                    checkbox.setAttribute('data-payer-toggle-bound', '1');
                    togglePayerFields();
                }
            }

            function ensureCouponUI() {
                var sect3 = document.querySelector('.woocommerce-checkout-payment, #payment');
                if (!sect3) return;

                var existingCoupon = sect3.querySelector('.woocommerce-coupon-form-wrapper');
                if (existingCoupon) existingCoupon.remove();

                if (window.mg_coupon_html) {
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = window.mg_coupon_html.trim();
                    var newCouponWrapper = tempDiv.firstChild;

                    if (newCouponWrapper) {
                        var placeOrderDiv = sect3.querySelector('.form-row.place-order');
                        if (placeOrderDiv) {
                            sect3.insertBefore(newCouponWrapper, placeOrderDiv);
                        } else {
                            sect3.appendChild(newCouponWrapper);
                        }
                        initCouponToggle();
                    }
                }
            }

            function initCouponToggle() {
                var wrap = document.querySelector('#payment .woocommerce-coupon-form-wrapper');
                if (!wrap) return;
                var toggleWrap = wrap.querySelector('.woocommerce-form-coupon-toggle');
                var toggleLink = toggleWrap ? toggleWrap.querySelector('a') : null;
                var form = wrap.querySelector('.checkout_coupon');
                if (!form) return;
                if (window.jQuery) {
                    jQuery(document).off('click', 'a.showcoupon');
                }
                var cancel = form.querySelector('.cly-coupon-cancel');
                if (!cancel) {
                    cancel = document.createElement('a');
                    cancel.href = '#';
                    cancel.className = 'cly-coupon-cancel';
                    cancel.textContent = 'Cancelar';
                    form.appendChild(cancel);
                }
                if (toggleWrap) toggleWrap.style.display = 'block';
                form.style.display = 'none';
                if (toggleLink) {
                    toggleLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (e.stopPropagation) e.stopPropagation();
                        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                        if (toggleWrap) toggleWrap.style.display = 'none';
                        form.style.display = 'block';
                        var inp = form.querySelector('input#coupon_code');
                        if (inp) {
                            try {
                                inp.focus();
                            } catch (_) {}
                        }
                    });
                }
                cancel.addEventListener('click', function(e) {
                    e.preventDefault();
                    form.style.display = 'none';
                    if (toggleWrap) toggleWrap.style.display = 'block';
                });
                syncCouponState();
            }

            function syncCouponState() {
                var wrap = document.querySelector('#payment .woocommerce-coupon-form-wrapper');
                if (!wrap) return;
                var toggleWrap = wrap.querySelector('.woocommerce-form-coupon-toggle');
                var form = wrap.querySelector('.checkout_coupon');
                var removeLink = document.querySelector('.woocommerce-remove-coupon');
                var banner = wrap.querySelector('.mg-coupon-applied');
                if (removeLink) {
                    var code = removeLink.getAttribute('data-coupon') || removeLink.textContent || '';
                    if (!banner) {
                        banner = document.createElement('div');
                        banner.className = 'mg-coupon-applied';
                        banner.innerHTML = '<div class="mg-ca-left"><strong>Cupón aplicado</strong><br><span class="mg-ca-code"></span></div><a href="#" class="mg-ca-remove">Quitar</a>';
                        wrap.appendChild(banner);
                    }
                    var codeEl = banner.querySelector('.mg-ca-code');
                    if (codeEl) codeEl.textContent = code.toUpperCase();
                    var rm = banner.querySelector('.mg-ca-remove');
                    if (rm) {
                        rm.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (removeLink) removeLink.click();
                        });
                    }
                    if (toggleWrap) toggleWrap.style.display = 'none';
                    if (form) form.style.display = 'none';
                    banner.style.display = 'block';
                } else {
                    if (banner) banner.remove();
                    if (toggleWrap) toggleWrap.style.display = 'block';
                    if (form) form.style.display = 'none';
                }
            }

            function buildBillingChoice() {
                if (!sect1) return;
                var wrap = sect1.querySelector('.woocommerce-billing-fields');
                if (!wrap || wrap.querySelector('.mg-billing-choice')) return;
                var choice = document.createElement('div');
                choice.className = 'mg-billing-choice';
                var no = document.createElement('div');
                no.className = 'mg-choice';
                no.innerHTML = '<div><strong>No, no requiero factura</strong><br><small>Continuar sin datos de facturación</small></div><div class="dot"></div>';
                var si = document.createElement('div');
                si.className = 'mg-choice';
                si.innerHTML = '<div><strong>Sí, requiero factura</strong><br><small>Proporcionar datos fiscales</small></div><div class="dot"></div>';
                choice.appendChild(no);
                choice.appendChild(si);
                var h = wrap.querySelector('h3');
                var legend = wrap.querySelector('.mg-billing-legend');
                if (!legend) {
                    legend = document.createElement('p');
                    legend.className = 'mg-billing-legend';
                    legend.textContent = '¿Requieres factura para esta compra?';
                    if (h) h.after(legend);
                }
                if (legend) legend.after(choice);
                else if (h && h.nextSibling) wrap.insertBefore(choice, h.nextSibling);
                else wrap.insertBefore(choice, wrap.firstChild);
                var chk = document.querySelector('#billing_requires');

                function setState(val) {
                    if (chk) {
                        chk.checked = val;
                        chk.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    }
                    no.classList.toggle('is-selected', !val);
                    si.classList.toggle('is-selected', val);
                }
                no.addEventListener('click', function() {
                    setState(false);
                });
                si.addEventListener('click', function() {
                    setState(true);
                });
                setState(chk ? chk.checked : false);
            }

            function setHeader(step) {
                steps.forEach(function(s) {
                    s.classList.remove('active', 'completed');
                    var n = parseInt(s.getAttribute('data-step'), 10);
                    if (n < step) s.classList.add('completed');
                    if (n === step) s.classList.add('active');
                });
                var lines = document.querySelectorAll('.mg-steps .mg-line');
                lines.forEach(function(l) {
                    l.classList.remove('is-done');
                });
                if (lines[0] && step > 1) lines[0].classList.add('is-done');
                if (lines[1] && step > 2) lines[1].classList.add('is-done');
            }

            function applyStep(step) {
                current = step;
                if (form) {
                    form.classList.remove('step-1', 'step-2', 'step-3');
                    form.classList.add('step-' + step);
                }
                setHeader(step);

                // Handle Step 1/2 specific DOM manipulations
                if (step === 2) buildBillingChoice();

                var mgBillingChoiceEl = sect1 && sect1.querySelector ? sect1.querySelector('.mg-billing-choice') : null;
                if (mgBillingChoiceEl) mgBillingChoiceEl.style.display = (step === 2 ? 'grid' : 'none');

                // var wcAdditionalFieldsEl = document.querySelector('.woocommerce-additional-fields');
                // if (step === 1 && wcAdditionalFieldsEl && sect1) {
                //     var col1 = sect1.querySelector('.col-1') || sect1;
                //     col1.appendChild(wcAdditionalFieldsEl);
                //     wcAdditionalFieldsEl.style.display = 'block';
                // }
                // if (step === 2 && wcAdditionalFieldsEl) {
                //     wcAdditionalFieldsEl.style.display = 'none';
                // }

                var h = sect1.querySelector('.woocommerce-billing-fields > h3');
                if (step === 1 && h) h.textContent = 'Información del paciente';
                if (step === 2 && h) h.textContent = 'Datos de facturación';
                var legend = sect1.querySelector('.mg-billing-legend');
                if (legend) legend.style.display = (step === 2 ? 'block' : 'none');

                // Handle Step 3 UI
                var sect3 = document.querySelector('#payment');
                if (step === 3) {
                    buildStep3UI();
                    ensureCouponUI();
                    showPayerInfoFields(true);
                } else {
                    // If not in step 3, ensure our UI is removed from the DOM
                    var extra = sect3 ? sect3.querySelector('.mg-extra-payments') : null;
                    if (extra) extra.remove();

                    var couponWrapper = document.querySelector('.woocommerce-coupon-form-wrapper');
                    if (couponWrapper) couponWrapper.remove();

                    showPayerInfoFields(false);
                }

                ensureControls();
            }

            function enhancePaymentSelection() {
                var container = document.querySelector('#payment');
                if (!container) return;
                var items = container.querySelectorAll('.wc_payment_method');

                function enforceSingleVisibleBox() {
                    // if (window.jQuery) {
                    //     var $selected = jQuery('#payment .wc_payment_method input.input-radio:checked');
                    //     jQuery('#payment .wc_payment_method .payment_box').hide();
                    //     if ($selected.length) {
                    //         var val = $selected.val();
                    //         var $target = jQuery('#payment .payment_box.payment_method_' + val);
                    //         if ($target.length) $target.stop(true, true).show();
                    //         else $selected.closest('.wc_payment_method').find('.payment_box').show();
                    //     }
                    //     return;
                    // }
                    // var selected = container.querySelector('.wc_payment_method input.input-radio:checked');
                    // var boxes = container.querySelectorAll('.wc_payment_method .payment_box');
                    // boxes.forEach(function(b) {
                    //     b.style.display = 'none';
                    // });
                    // if (selected) {
                    //     var val = selected.value || '';
                    //     var target = container.querySelector('.payment_box.payment_method_' + val);
                    //     if (target) target.style.display = '';
                    //     else {
                    //         var li = selected.closest('.wc_payment_method');
                    //         var box = li ? li.querySelector('.payment_box') : null;
                    //         if (box) box.style.display = '';
                    //     }
                    // }
                }

                function scheduleEnforce() {
                    // setTimeout(enforceSingleVisibleBox, 50);
                    // setTimeout(enforceSingleVisibleBox, 250);
                    // setTimeout(enforceSingleVisibleBox, 500);
                }
                window.clyScheduleEnforce = scheduleEnforce;

                function isCard(li) {
                    var label = li ? li.querySelector('label') : null;
                    var txt = (label && label.textContent ? label.textContent : '').toLowerCase();
                    return txt.indexOf('tarjeta') !== -1 || txt.indexOf('tarjetas') !== -1 || txt.indexOf('card') !== -1;
                }

                // function selectCard() {
                //     var preferred = null;
                //     items.forEach(function(li) {
                //         if (!preferred && isCard(li)) preferred = li;
                //     });
                //     if (!preferred && items.length) preferred = items[0];
                //     if (preferred) {
                //         var inp = preferred.querySelector('input.input-radio');
                //         var box = preferred.querySelector('.payment_box');
                //         if (box) box.style.display = '';
                //         if (inp) {
                //             if (window.jQuery) {
                //                 jQuery(inp).prop('checked', true).trigger('change');
                //             } else {
                //                 inp.checked = true;
                //                 inp.dispatchEvent(new Event('change', {
                //                     bubbles: true
                //                 }));
                //             }
                //         }
                //     }
                // }

                function applySelected() {
                    items.forEach(function(o) {
                        o.classList.remove('is-selected');
                    });
                    var checked = container.querySelector('.wc_payment_method input.input-radio:checked');
                    var shouldShow = !!(checked || window.clyUserSelectedPayment || window.clyCombineSelected);
                    var boxes = container.querySelectorAll('.payment_box');
                    boxes.forEach(function(b) {
                        b.style.display = 'none';
                    });
                    if (checked) {
                        var li = checked.closest('.wc_payment_method');
                        if (li && shouldShow) li.classList.add('is-selected');
                        var val = checked.value || '';
                        var target = container.querySelector('.payment_box.payment_method_' + val);
                        if (target) {
                            target.style.display = '';
                        } else if (li) {
                            var box = li.querySelector('.payment_box');
                            if (box) box.style.display = '';
                        }
                    }
                }
                items.forEach(function(li) {
                    var input = li.querySelector('input.input-radio');
                    if (!input) return;
                    if (input.getAttribute('data-cly-bound') === '1') return;
                    input.addEventListener('change', function() {
                        // Not required for redesign
                        // if (window.clyCombineSelected) {
                        //     var cm = container.querySelector('.mg-combine-block');
                        //     if (!isCard(li)) {
                        //         window.clyCombineSelected = 0;
                        //         if (cm) cm.classList.remove('is-selected');
                        //     } else {
                        //         window.clyCombineSelected = 0;
                        //         if (cm) cm.classList.remove('is-selected');
                        //     }
                        // }
                        window.clyUserSelectedPayment = 1;
                        window.clyLastPaymentMethod = input.value || '';
                        // Dejar que WooCommerce controle la expansión del payment_box con su propio manejador de change
                        applySelected();
                        setTimeout(ensureConektaIframeVisible, 50);
                        // if (window.jQuery) {
                        //     var $ = jQuery;
                        //     var val = $(input).val();
                        //     $('#payment .wc_payment_method .payment_box').stop(true, true).hide();
                        //     var $target = $('#payment .payment_box.payment_method_' + val);
                        //     if ($target.length) $target.stop(true, true).show();
                        //     else $(input).closest('.wc_payment_method').find('.payment_box').stop(true, true).show();
                        // } else {
                        //     scheduleEnforce();
                        // }
                    });
                    li.addEventListener('click', function(e) {
                        if (e.target.closest('.payment_box')) return;
                        if (window.clyCombineSelected) {
                            var cm = container.querySelector('.mg-combine-block');
                            window.clyCombineSelected = 0;
                            if (cm) cm.classList.remove('is-selected');
                        }
                        window.clyUserSelectedPayment = 1;
                        window.clyLastPaymentMethod = input.value || '';
                        // No forzar manualmente display; el change de WooCommerce se encarga
                        if (window.jQuery) {
                            jQuery(input).prop('checked', true);
                        }
                        input.checked = true;
                        input.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                        setTimeout(ensureConektaIframeVisible, 50);
                        // if (window.jQuery) {
                        //     var $ = jQuery;
                        //     var val = $(input).val();
                        //     $('#payment .wc_payment_method .payment_box').stop(true, true).hide();
                        //     var $target = $('#payment .payment_box.payment_method_' + val);
                        //     if ($target.length) $target.stop(true, true).show();
                        //     else $(input).closest('.wc_payment_method').find('.payment_box').stop(true, true).show();
                        // } else {
                        //     scheduleEnforce();
                        // }
                    });
                    input.setAttribute('data-cly-bound', '1');
                });
                applySelected();
                setTimeout(ensureConektaIframeVisible, 50);

                // Not required for redesign
                // var combine = container.querySelector('.mg-combine-block');
                // if (combine) {
                //     if (combine.getAttribute('data-cly-bound') !== '1') {
                //         combine.addEventListener('click', function() {
                //             var radios = container.querySelectorAll('.wc_payment_method input.input-radio');
                //             if (window.jQuery) {
                //                 jQuery(radios).prop('checked', false);
                //             } else {
                //                 radios.forEach(function(r) {
                //                     r.checked = false;
                //                 });
                //             }
                //             // No ocultar manualmente; seleccionaremos Tarjeta y WooCommerce mostrará su payment_box
                //             window.clyCombineSelected = 1;
                //             window.clyUserSelectedPayment = 1;
                //             var cb = container.querySelector('.mg-cashback-card');
                //             if (cb) cb.classList.remove('is-selected');
                //             combine.classList.add('is-selected');
                //             items.forEach(function(o) {
                //                 o.classList.remove('is-selected');
                //             });
                //             // selectCard();
                //             var selected = container.querySelector('.wc_payment_method input.input-radio:checked');
                //             if (selected) {
                //                 window.clyLastPaymentMethod = selected.value || '';
                //             }
                //             scheduleEnforce();
                //         });
                //         combine.setAttribute('data-cly-bound', '1');
                //     }
                // }

                // Not required for redesign
                // var cashback = container.querySelector('.mg-cashback-card');
                // if (cashback) {
                //     if (cashback.getAttribute('data-cly-bound') !== '1') {
                //         cashback.addEventListener('click', function() {
                //             window.clyCombineSelected = 0;
                //             var cm = container.querySelector('.mg-combine-block');
                //             if (cm) cm.classList.remove('is-selected');
                //             cashback.classList.add('is-selected');
                //             items.forEach(function(o) {
                //                 o.classList.remove('is-selected');
                //             });
                //             window.clyUserSelectedPayment = 0;
                //             window.clyLastPaymentMethod = '';
                //             var radios = container.querySelectorAll('.wc_payment_method input.input-radio');
                //             if (window.jQuery) {
                //                 jQuery(radios).prop('checked', false);
                //             } else {
                //                 radios.forEach(function(r) {
                //                     r.checked = false;
                //                 });
                //             }
                //             setTimeout(function() {
                //                 var boxes = container.querySelectorAll('.wc_payment_method .payment_box');
                //                 boxes.forEach(function(b) {
                //                     b.style.display = 'none';
                //                 });
                //             }, 0);
                //         });
                //         cashback.setAttribute('data-cly-bound', '1');
                //     }
                // }
            }

            // Not required for redesign
            function reapplyCombineSelection() {
            //     var sect3 = document.querySelector('#payment');
            //     if (!sect3) return;
            //     if (window.clyCombineSelected) {
            //         var cm = sect3.querySelector('.mg-combine-block');
            //         if (cm) cm.classList.add('is-selected');
            //     }
            }

            // Not required for redesign
            function syncCashbackUI() {
                var wrap = document.querySelector('#payment .mg-extra-payments');
                if (!wrap) return;
                var input = wrap.querySelector('.mg-cashback-amount');
                var hint = wrap.querySelector('.mg-cashback-input .hint');
                var hiddenApply = wrap.querySelector('.cly-apply-cashback');
                var hiddenAmount = wrap.querySelector('.cly-cashback-amount');
                if (!input || !hint) return;
                if (!input.getAttribute('data-max-original')) {
                    input.setAttribute('data-max-original', input.getAttribute('data-max') || '0');
                }
                var orig = parseFloat(input.getAttribute('data-max-original') || '0');
                var val = parseFloat(input.value || '0');
                var remaining = Math.max(orig - (isNaN(val) ? 0 : val), 0);
                input.setAttribute('data-max', String(remaining));
                input.setAttribute('max', String(remaining));
                hint.textContent = 'Máximo disponible: ' + remaining.toFixed(2);
            }

            // Not required for redesign
            function setCashbackState(apply, amount) {
                window.clyCashbackState = window.clyCashbackState || {};
                window.clyCashbackState.apply = apply ? 1 : 0;
                window.clyCashbackState.amount = isNaN(parseFloat(amount)) ? 0 : parseFloat(amount);
            }

            // Not required for redesign
            function restoreCashbackState() {
                var state = window.clyCashbackState;
                if (!state) return;
                var wrap = document.querySelector('#payment .mg-extra-payments');
                if (!wrap) return;
                var input = wrap.querySelector('.mg-cashback-amount');
                var hint = wrap.querySelector('.mg-cashback-input .hint');
                var hiddenApply = wrap.querySelector('.cly-apply-cashback');
                var hiddenAmount = wrap.querySelector('.cly-cashback-amount');
                if (!input) return;
                if (!input.getAttribute('data-max-original')) {
                    input.setAttribute('data-max-original', input.getAttribute('data-max') || '0');
                }
                var orig = parseFloat(input.getAttribute('data-max-original') || input.getAttribute('data-max') || '0');
                var amt = isNaN(state.amount) ? 0 : state.amount;
                input.value = String(amt);
                if (hiddenApply) hiddenApply.value = String(state.apply || 0);
                if (hiddenAmount) hiddenAmount.value = String(amt);
                var remaining = Math.max(orig - amt, 0);
                input.setAttribute('data-max', String(remaining));
                input.setAttribute('max', String(remaining));
                if (hint) hint.textContent = 'Máximo disponible: ' + remaining.toFixed(2);
            }

            // Not required for redesign
            function initCashbackActions() {
                document.addEventListener('click', function(e) {
                    var a = e.target.closest('.mg-use-all');
                    if (!a) return;
                    e.preventDefault();
                    var wrap = document.querySelector('#payment .mg-extra-payments');
                    if (!wrap) return;
                    var input = wrap.querySelector('.mg-cashback-amount');
                    var hint = wrap.querySelector('.mg-cashback-input .hint');
                    var hiddenApply = wrap.querySelector('.cly-apply-cashback');
                    var hiddenAmount = wrap.querySelector('.cly-cashback-amount');
                    if (!input) return;
                    var orig = parseFloat(input.getAttribute('data-max-original') || input.getAttribute('data-max') || '0');
                    if (isNaN(orig)) orig = 0;
                    var amount = orig;
                    if (isNaN(amount)) return;
                    if (!input.getAttribute('data-max-original')) {
                        input.setAttribute('data-max-original', input.getAttribute('data-max') || '0');
                    }
                    input.value = amount;
                    setCashbackState(1, amount);
                    input.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                    input.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    if (hiddenApply) hiddenApply.value = '1';
                    if (hiddenAmount) hiddenAmount.value = String(amount);
                    if (window.jQuery) {
                        jQuery(document.body).trigger('update_checkout');
                    }
                    var remaining = Math.max(orig - amount, 0);
                    if (hint) {
                        hint.textContent = 'Máximo disponible: ' + remaining.toFixed(2);
                    }
                    input.setAttribute('data-max', String(remaining));
                    input.setAttribute('max', String(remaining));
                    var cm = wrap.querySelector('.mg-combine-block');
                    var cb = wrap.querySelector('.mg-cashback-card');
                    if (cm) cm.classList.add('is-selected');
                    if (cb) cb.classList.remove('is-selected');
                    var sect3 = document.querySelector('#payment');
                    if (sect3) {
                        var items = sect3.querySelectorAll('.wc_payment_method');
                        items.forEach(function(o) {
                            o.classList.remove('is-selected');
                        });
                        var checked = sect3.querySelector('.wc_payment_method input.input-radio:checked');
                        if (!checked && items[0]) {
                            var inp = items[0].querySelector('input.input-radio');
                            if (inp) {
                                inp.checked = true;
                                inp.dispatchEvent(new Event('change', {
                                    bubbles: true
                                }));
                            }
                        }
                    }
                }, {
                    passive: false
                });
                document.addEventListener('change', function(e) {
                    var inp = e.target.closest('#payment .mg-cashback-amount');
                    if (!inp) return;
                    var wrap = document.querySelector('#payment .mg-extra-payments');
                    if (!wrap) return;
                    var hiddenApply = wrap.querySelector('.cly-apply-cashback');
                    var hiddenAmount = wrap.querySelector('.cly-cashback-amount');
                    var val = parseFloat(inp.value || '0');
                    if (!inp.getAttribute('data-max-original')) {
                        inp.setAttribute('data-max-original', inp.getAttribute('data-max') || '0');
                    }
                    var orig = parseFloat(inp.getAttribute('data-max-original') || '0');
                    var remaining = Math.max(orig - (isNaN(val) ? 0 : val), 0);
                    var hint = wrap.querySelector('.mg-cashback-input .hint');
                    inp.setAttribute('data-max', String(remaining));
                    inp.setAttribute('max', String(remaining));
                    if (hint) hint.textContent = 'Máximo disponible: ' + remaining.toFixed(2);
                    setCashbackState(val > 0 ? 1 : 0, val);
                    if (hiddenApply) hiddenApply.value = val > 0 ? '1' : '0';
                    if (hiddenAmount) hiddenAmount.value = String(isNaN(val) ? 0 : val);
                    if (window.jQuery) {
                        jQuery(document.body).trigger('update_checkout');
                    }
                });
            }

            function ensureControls() {
                var ctr = document.querySelector('.mg-controls');
                if (!ctr) {
                    ctr = document.createElement('div');
                    ctr.className = 'mg-controls';
                    var prev = document.createElement('button');
                    prev.type = 'button';
                    prev.className = 'button mg-prev';
                    prev.textContent = 'Anterior';
                    var next = document.createElement('button');
                    next.type = 'button';
                    next.className = 'button alt mg-next';
                    next.textContent = 'Continuar';
                    ctr.appendChild(prev);
                    ctr.appendChild(next);
                    prev.addEventListener('click', function() {
                        if (current > 1) applyStep(current - 1);
                    });
                    next.addEventListener('click', function() {
                        if (current < 3) applyStep(current + 1);
                    });
                }
                // Fallback: garantizar que exista el botón "Anterior" aunque otro script haya alterado el DOM
                var prevBtn = ctr.querySelector('.mg-prev');
                if (!prevBtn) {
                    prevBtn = document.createElement('button');
                    prevBtn.type = 'button';
                    prevBtn.className = 'button mg-prev';
                    prevBtn.textContent = 'Anterior';
                    prevBtn.addEventListener('click', function() {
                        if (current > 1) applyStep(current - 1);
                    });
                    ctr.insertBefore(prevBtn, ctr.firstChild);
                }
                // Colocar controles en el contenedor visible según el paso
                if (current === 3 && sect3) {
                    // Ubicar controles al final, dentro de la fila .place-order
                    var placeRow = sect3.querySelector('.form-row.place-order');
                    if (placeRow) {
                        placeRow.appendChild(ctr);
                    } else {
                        sect3.appendChild(ctr);
                    }
                    var placeBtn = sect3.querySelector('#place_order');
                    if (placeBtn && !ctr.contains(placeBtn)) {
                        ctr.appendChild(placeBtn);
                        placeBtn.classList.add('alt');
                    }
                } else if (sect1) {
                    sect1.appendChild(ctr);
                }
                ctr.classList.remove('is-step-1', 'is-step-2', 'is-step-3');
                ctr.classList.add('is-step-' + current);
                ctr.style.display = 'flex';
            }

            function markCashbackFee() {
                var table = document.querySelector('.woocommerce-checkout-review-order-table');
                if (!table) return;
                var rows = table.querySelectorAll('tr.fee');
                rows.forEach(function(row) {
                    var th = row.querySelector('th');
                    if (!th) return;
                    var txt = (th.textContent || '').trim().toLowerCase();
                    if (txt.indexOf('cashback aplicado') !== -1) {
                        row.classList.add('cly-cashback-fee');
                        var amountCell = row.querySelector('td');
                        if (amountCell && !amountCell.querySelector('.cly-cancel-cashback')) {
                            var cancel = document.createElement('a');
                            cancel.href = '#';
                            cancel.className = 'cly-cancel-cashback';
                            cancel.setAttribute('aria-label', 'Cancelar cashback');
                            cancel.textContent = '✕';
                            amountCell.appendChild(cancel);
                        }
                    }
                });
            }

            function setupCashbackCancel() {
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('.cly-cancel-cashback');
                    if (!btn) return;
                    e.preventDefault();
                    var wrap = document.querySelector('#payment .mg-extra-payments');
                    if (!wrap) return;
                    var input = wrap.querySelector('.mg-cashback-amount');
                    var hint = wrap.querySelector('.mg-cashback-input .hint');
                    var hiddenApply = wrap.querySelector('.cly-apply-cashback');
                    var hiddenAmount = wrap.querySelector('.cly-cashback-amount');
                    if (hiddenApply) hiddenApply.value = '0';
                    if (hiddenAmount) hiddenAmount.value = '0';
                    setCashbackState(0, 0);
                    if (input) {
                        var orig = parseFloat(input.getAttribute('data-max-original') || '0');
                        input.value = '0';
                        input.setAttribute('data-max', String(orig));
                        input.setAttribute('max', String(orig));
                        if (hint) {
                            hint.textContent = 'Máximo disponible: ' + (isNaN(orig) ? '0.00' : orig.toFixed(2));
                        }
                    }
                    if (window.jQuery) {
                        jQuery(document.body).trigger('update_checkout');
                    }
                }, {
                    passive: false
                });
            }
            steps.forEach(function(s) {
                s.addEventListener('click', function() {
                    var n = parseInt(this.getAttribute('data-step'), 10);
                    applyStep(n);
                });
            });
            applyStep(1);
            ensureControls();
            placeWideMessage();
            enhancePaymentSelection();
            initCouponToggle();
            initCashbackActions();
            syncCashbackUI();
            markCashbackFee();
            setupCashbackCancel();
            restoreCashbackState();
            reapplyCombineSelection();

            if (window.jQuery) {
                jQuery(function($) {
                    $(document).on('change', 'input[name="payment_method"]', function() {
                        var $li = $(this).closest('.wc_payment_method');
                        var txt = ($li.find('label').text() || '').toLowerCase();
                        var isCard = txt.indexOf('tarjeta') !== -1 || txt.indexOf('tarjetas') !== -1 || txt.indexOf('card') !== -1;
                        if (!isCard) {
                            window.clyCombineSelected = 0;
                            $('.mg-combine-block').removeClass('is-selected');
                        }
                        if (window.clyScheduleEnforce) {
                            window.clyScheduleEnforce();
                        }
                    });
                    $(document.body).on('updated_checkout', function() {
                        if (current === 3) {
                            buildStep3UI();
                            if (window.clyLastPaymentMethod) {
                                var $inp = $('#payment .wc_payment_method input.input-radio[value="' + window.clyLastPaymentMethod + '"]');
                                if ($inp.length) {
                                    $inp.prop('checked', true);
                                }
                            }
                            enhancePaymentSelection();
                            ensureCouponUI();
                            initCouponToggle();
                            initCashbackActions();
                            ensureControls();
                            syncCouponState();
                            syncCashbackUI();
                            markCashbackFee();
                            setupCashbackCancel();
                            restoreCashbackState();
                            reapplyCombineSelection();
                            setTimeout(ensureConektaIframeVisible, 50);
                            setTimeout(ensureConektaIframeVisible, 300);
                        }
                    });
                });
            }
            // Al entrar a paso 3, asegurar que el botón "Realizar pedido" nativo quede a la derecha
            var mo = new MutationObserver(function() {
                if (current !== 3) return;
                var placeBtn = document.querySelector('#payment #place_order');
                var ctr = document.querySelector('.mg-controls');
                if (placeBtn && ctr && placeBtn.parentElement) {
                    ctr.classList.add('is-step-3');
                    // La fila ya tiene el botón; nuestros controles quedan a la izquierda
                    buildStep3UI();
                    enhancePaymentSelection();
                    ensureCouponUI();
                }
            });
            if (sect3) mo.observe(sect3, {
                childList: true,
                subtree: true
            });
        })();
    </script>
<?php
}

add_filter('woocommerce_order_button_text', function ($text) {
    if (function_exists('is_checkout') && is_checkout()) {
        return 'Confirmar pago';
    }
    return $text;
});
