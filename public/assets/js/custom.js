"use strict";
/**
 *
 * You can write your JS code here, DO NOT touch the default style file
 * because it will make it harder for you to update.
 *
 */

function show_toastr(title, message, type) {
    var o, i;
    var icon = "";
    var cls = "";
    var toster_pos = "right";
    if (type == "success") {
        icon = "fas fa-check-circle";
        cls = "success";
    } else if (type == "warning") {
        icon = "fas fa-check-circle";
        cls = "warning";
    } else if (type == "info") {
        icon = "fas fa-check-circle";
        cls = "info";
    } else {
        icon = "fas fa-times-circle";
        cls = "danger";
    }

    $.notify({
        icon: icon,
        title: " " + title,
        message: message,
        url: ""
    }, {
        element: "body",
        type: cls,
        allow_dismiss: !0,
        placement: {
            from: "top",
            align: toster_pos,
        },
        offset: {
            x: 15,
            y: 15
        },
        spacing: 10,
        z_index: 1080,
        delay: 2500,
        timer: 2000,
        url_target: "_blank",
        mouse_over: !1,
        animate: {
            enter: o,
            exit: i
        },
        template: '<div class="toast text-white bg-' +
            cls +
            ' fade show" role="alert" aria-live="assertive" aria-atomic="true">' +
            '<div class="d-flex">' +
            '<div class="toast-body"> ' +
            message +
            " </div>" +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            "</div>" +
            "</div>",
    });
}
$(document).ready(function () {
    $(document).on("click", ".show_confirm", function (event) {
        var form = $(this).closest("form");
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: "btn btn-success",
                cancelButton: "btn btn-danger",
            },
            buttonsStyling: false,
        });
        swalWithBootstrapButtons
            .fire({
                title: "Are you sure?",
                text: "This action can not be undone. Do you want to continue?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes",
                cancelButtonText: "No",
                reverseButtons: true,
            })
            .then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
    });

    $(document).on("change", ".chnageStatus", function (e) {
        var csrf = $("meta[name=csrf-token]").attr("content");
        var value = $(this).is(":checked");
        var action = $(this).data("url");
        $.ajax({
            type: "POST",
            url: action,
            data: {
                _token: csrf,
                value: value,
            },
            success: function (response) {
                if (response.warning) {
                    show_toastr("Warning!", response.warning, "warning");
                    return false;
                }
                if (response.is_success) {
                    show_toastr("Success!", response.message, "success");
                }
            },
        });
    });
    $(document).on("click", ".event-tag label", function () {
        $(".event-tag label").removeClass("active");
        $(this).addClass("active");
    });
    if ($(".pc-dt-simple").length > 0) {
        $($(".pc-dt-simple")).each(function (index, element) {
            var id = $(element).attr("id");
            const dataTable = new simpleDatatables.DataTable("#" + id);
        });
    }
});

// save buttton loader
$(document).ready(function () {
    $(document).on('click', 'button[type="submit"]', function (event) {
        var submitBtn = $(this);
        submitBtn.text("Please Wait...");
        setTimeout(function() {
            submitBtn.text("Save"); // Restore the original button text
        }, 1000); // 1000 milliseconds = 1 second
    });
});


// function changeCompany(companyId, companyName) {
//     console.log('Selected Company ID:', companyId);
//     if (!companyId) {
//         return;
//     }
//     const url = `/fetch-company-data/${companyId}`; 
//     console.log('Request URL:', url);

//     fetch(url)
//         .then(response => {
//             if (!response.ok) {
//                 return Promise.reject(new Error('Network response was not ok: ' + response.statusText));
//             }
//             return response.json();
//         })
//         .then(data => {
//             if (data && data.company) { 
//                 document.querySelector('.company-changes').textContent = data.company.name;
//                 localStorage.setItem('selectedCompanyId', companyId);

//                 return fetch('/set-company-session', {
//                     method: 'POST',
//                     headers: {
//                         'Content-Type': 'application/json',
//                         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
//                     },
//                     body: JSON.stringify({
//                         company_id: companyId,
//                         company_name: companyName
//                     }),
//                 });
//             } else {
//                 console.warn('Company data not found'); // Changed to console.warn
//                 return Promise.reject(new Error('Company data not found'));
//             }
//         })
//         .then(response => {
//             if (!response.ok) {
//                 return Promise.reject(new Error('Failed to update session: ' + response.statusText));
//             }
//             const contentType = response.headers.get("content-type");
//             if (!contentType || !contentType.includes("application/json")) {
//                 return Promise.reject(new Error("Response is not JSON: " + response.statusText));
//             }
//             return response.json();
//         })
//         .then(sessionData => {
//             if (sessionData.success) {
//                 console.log('Session updated with company:', sessionData.company);
//             } else {
//                 console.warn('Failed to update session.');
//             }
//         })
//         .catch(error => {
//             console.error('Error:', error.message);
//         });
//     if (window.location.href.indexOf(`companyData=${companyId}`) === -1) {
//         window.location.search = `?companyData=${companyId}`;
//     }
// }


