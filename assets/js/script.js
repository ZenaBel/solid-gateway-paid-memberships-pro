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

        if (subscriptionId) {
            const $button = $('<button/>', {
                text: 'Restore',
                class: 'page-title-action'
            });
            $.ajax({
                url: pmpro_solid.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_solid_gateway_restore_button',
                    id: subscriptionId
                },
                success: function (response) {
                    response = JSON.parse(response);
                    console.log(response);
                    if (response.status === 'success') {
                        $('.page-title-action.pmpro-has-icon-update').after($button);
                        $button.attr('data-restore-url', response.restore_url);
                        $button.on('click', function () {
                            window.location.href = $(this).data('restore-url');
                        });
                    } else {
                        console.error('Error fetching update URL:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        } else {
            console.warn("ID subscription not found in URL.");
        }
    } else {
        console.warn("Not on the correct page.  URL does not match.");
    }
});
