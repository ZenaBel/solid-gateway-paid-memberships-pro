// jQuery(document).ready(function($) {
//     $('#pmpro_form').on('submit', function(e) {
//         console.log('Button clicked');
//         console.log($('input[name="gateway"]').val());
//         if ($( 'input[name="gateway"]' ).length > 0 && $( 'input[name="gateway"]' ).val() !== 'solid') {
//             return;
//         }
//
//         if ( e.isDefaultPrevented() ) {
//             return;
//         }
//
//         e.preventDefault(); // Забороняємо стандартну поведінку кнопки
//
//         // Збираємо всі дані з форми
//         var formData = $('#pmpro_form').serialize();
//
//         // Отримуємо вибраний метод оплати
//         var paymentMethod = $('input[name="gateway"]').val();
//
//         // Виконуємо AJAX-запит або робимо редірект на сторінку оплати
//         if (paymentMethod === 'solid') {
//             // Якщо вибрано Solid Payment, відправляємо дані через AJAX
//             $.ajax({
//                 url: pmpro_solid.ajax_url, // URL для обробки AJAX
//                 type: 'POST',
//                 data: {
//                     action: 'process', // Дія для обробки
//                     form_data: formData
//                 },
//                 success: function(response) {
//                     console.log(response); // Лог для налагодження
//                     console.log(response.data); // Лог для налагодження
//                     if (response.success) {
//                         // Наприклад, редірект на сторінку оплати
//                         window.location.href = response.data.payment_url;
//                     } else {
//                         alert('Помилка під час обробки оплати');
//                     }
//                 }
//             });
//         } else {
//             // Інший метод оплати (якщо він передбачає прямий редірект)
//             window.location.href = '/another-payment-gateway-url';
//         }
//     });
// });
jQuery(document).ready(function ($) {
    const currentUrl = window.location.href;
    if (currentUrl.includes('/wp-admin/admin.php') && currentUrl.includes('page=pmpro-subscriptions')) {
        const urlParams = new URLSearchParams(currentUrl);
        const subscriptionId = urlParams.get('id');

        if (!subscriptionId) {
            console.warn("ID subscription not found in URL.");
            return;
        }

        // HTML для попапу
        const modal = `
            <div id="pause-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
                background:#fff; padding:20px; border:1px solid #ccc; z-index:9999;">
                <label>Дата початку:<input type="date" id="pause-start"></label><br><br>
                <label>Дата кінця:<input type="date" id="pause-end"></label><br><br>
                <div id="pause-error" style="color:red; margin-bottom:10px;"></div>
                <button id="pause-submit" style="border-color: #0a4b78; color: #0a4b78; margin: 0 calc( var(--pmpro--spacing--small) / 2 ); display: inline-block; position: relative; box-sizing: border-box; cursor: pointer; white-space: nowrap; text-decoration: none; text-shadow: none; font-family: inherit;    ">Надіслати</button>
                <button id="pause-close" style="border-color: #0a4b78; color: #0a4b78; margin: 0 calc( var(--pmpro--spacing--small) / 2 ); display: inline-block; position: relative; box-sizing: border-box; cursor: pointer; white-space: nowrap; text-decoration: none; text-shadow: none; font-family: inherit; ">Закрити</button>
            </div>
        `;
        $('body').append(modal);

        $.ajax({
            url: pmpro_solid.ajax_url,
            type: 'POST',
            data: {
                action: 'solid_gateway_subscription_info',
                id: subscriptionId
            },
            success: function (response) {
                console.log(response);
                if (response.success) {
                    if (!response.data.allowed) {
                        return;
                    }
                    const restoreUrl = response.data.restore_url;
                    const isPaused = response.data.is_paused;
                    const pausedHidden = response.data.paused_hidden;
                    console.log(restoreUrl, isPaused, pausedHidden);

                    if (restoreUrl) {
                        const $restoreBtn = $('<button/>', {
                            text: 'Restore',
                            class: 'page-title-action'
                        }).attr('data-restore-url', restoreUrl);

                        $restoreBtn.on('click', function () {
                            window.location.href = $(this).data('restore-url');
                        });

                        $('.page-title-action.pmpro-has-icon-update').after($restoreBtn);
                    }

                    if (!pausedHidden) {
                        // Кнопка паузи або скасування паузи
                        const $pauseBtn = $('<button/>', {
                            id: isPaused ? 'unpause-btn' : 'pause-btn',
                            class: 'page-title-action',
                            text: isPaused ? 'Скасувати паузу' : 'Встановити паузу'
                        });

                        $('.page-title-action.pmpro-has-icon-update').after($pauseBtn);
                    }
                } else {
                    console.error('Error fetching update URL:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });

        // Відкриття попапу
        $(document).on('click', '#pause-btn', function () {
            $('#pause-error').text('');
            $('#pause-modal').fadeIn();
        });

        // Закриття попапу
        $(document).on('click', '#pause-close', function () {
            $('#pause-modal').fadeOut();
        });

        // Надсилання запиту на встановлення паузи
        $(document).on('click', '#pause-submit', function () {
            const data = {
                action: 'solid_gateway_set_pause',
                start_date: $('#pause-start').val(),
                end_date: $('#pause-end').val(),
                subscription_id: subscriptionId
            };

            $.post(pmpro_solid.ajax_url, data)
                .done(function (response) {
                    if (response.success) {
                        $('#pause-modal').fadeOut();
                        $('#pause-btn').text('Скасувати паузу').attr('id', 'unpause-btn');
                    } else {
                        $('#pause-error').text(response.data);
                    }
                })
                .fail(function () {
                    $('#pause-error').text('Помилка з’єднання.');
                });
        });

        // Обробка скасування паузи
        $(document).on('click', '#unpause-btn', function () {
            $.post(pmpro_solid.ajax_url, {
                action: 'solid_gateway_cancel_pause',
                subscription_id: subscriptionId
            })
                .done(function (response) {
                    if (response.success) {
                        $('#unpause-btn').text('Встановити паузу').attr('id', 'pause-btn');
                    } else {
                        alert(response.data || 'Не вдалося скасувати паузу');
                    }
                })
                .fail(function () {
                    alert('Помилка з’єднання.');
                });
        });
    } else {
        console.warn("Not on the correct page.  URL does not match.");
    }
});
