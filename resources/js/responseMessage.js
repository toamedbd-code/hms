import { router } from '@inertiajs/vue3';

let toastrPromise;
let sweetAlertPromise;

const loadToastr = () => {
    if (!toastrPromise) {
        toastrPromise = import('toastr')
            .then(({ default: toastr }) => {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    preventDuplicates: true,
                    positionClass: toastr.options?.positionClass || 'toast-top-right',
                    timeOut: 3500,
                    extendedTimeOut: 1000,
                };
                window.toastr = toastr;
                return toastr;
            })
            .catch((error) => {
                toastrPromise = undefined;
                throw error;
            });
    }

    return toastrPromise;
};

const loadSweetAlert = () => {
    if (!sweetAlertPromise) {
        sweetAlertPromise = import('sweetalert2')
            .then(({ default: Swal }) => Swal)
            .catch((error) => {
                sweetAlertPromise = undefined;
                throw error;
            });
    }

    return sweetAlertPromise;
};

export const showToast = (type, message) => {
    return loadToastr()
        .then((toastr) => toastr[type](message))
        .catch((error) => {
            console.error('Unable to load the notification library.', error);
            if (typeof window !== 'undefined') {
                window.alert(String(message || ''));
            }
        });
};

export const successMessage = (message) => {
    showToast('success', message);
};

export const infoMessage = (message) => {
    showToast('info', message);
};

export const warningMessage = (message) => {
    showToast('warning', message);
};

export const errorMessage = (message) => {
    showToast('error', message);
};

export const displayWarning = (errors) => {
    const errorKeys = Object.keys(errors);

    errorKeys.forEach((errorKey) => {
        warningMessage(errors[errorKey]);
    });
};


export const displayResponse = (response) => {
    // Signal the global inertia:success handler to skip (avoid duplicate toast)
    try { if (typeof window !== 'undefined') window.__inertiaMarkToastShown?.(); } catch (_) {}

    // Inertia responses include flash messages under props.flash
    if (response?.props?.flash?.successMessage) {
        successMessage(response.props.flash.successMessage);
        return;
    }

    if (response?.props?.flash?.errorMessage) {
        errorMessage(response.props.flash.errorMessage);
        return;
    }

    // Non-Inertia plain JSON responses (e.g., { successMessage: '...' })
    if (response?.successMessage) {
        successMessage(response.successMessage);
        return;
    }

    if (response?.errorMessage) {
        errorMessage(response.errorMessage);
        return;
    }

    // Axios-style responses may wrap data under `data`
    if (response?.data?.successMessage) {
        successMessage(response.data.successMessage);
        return;
    }

    if (response?.data?.errorMessage) {
        errorMessage(response.data.errorMessage);
        return;
    }
};

// Show toast only when there is no server-side flash message present.
export const showToastIfNoFlash = (response) => {
    if (!response?.props?.flash?.successMessage && !response?.props?.flash?.errorMessage) {
        displayResponse(response);
    }
};

export const successAlert = (title, message) => {
    return loadSweetAlert().then((Swal) => Swal.fire({
        title,
        text: message,
        icon: 'success',
    }));
};

export const failAlert = (title, message) => {
    return loadSweetAlert().then((Swal) => Swal.fire({
        title,
        text: message,
        icon: 'error',
    }));
};

const showCancelledAlert = (Swal) => {
    return Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger',
        },
        buttonsStyling: false,
    }).fire({
        title: 'Cancelled',
        text: 'Your imaginary data is safe :)',
        icon: 'error',
    });
};

export const statusChangeConfirmation = (url) => {
    return loadSweetAlert().then((Swal) => Swal.fire({
        title: "Warning",
        text: "Are you sure you want to change the status?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, change it!"
    }).then((result) => {
        if (result.isConfirmed) {
            try {
                router.
                    visit(url, {
                        onSuccess: (response) => {
                            displayResponse(response);
                        },
                        onError: (errorObject) => {
                            displayWarning(errorObject);
                        },
                    });

            } catch (error) {
                errorMessage("An error occurred while changing status.");
            }
        }
        else if (result.dismiss === Swal.DismissReason.cancel) {
            return showCancelledAlert(Swal);
        }
    }));
};

export const deleteConfirmation = (url) => {
    return loadSweetAlert().then((Swal) => Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, Delete it!"
    }).then((result) => {

        if (result.isConfirmed) {
            try {
                router.
                    post(url, {
                        _method: 'delete',
                    }, {
                        onSuccess: (response) => {
                            displayResponse(response);
                        },
                        onError: (errorObject) => {
                            displayWarning(errorObject);
                        },
                    });

            } catch (error) {
                console.dir(error);
                errorMessage("An error occurred while changing status.");
            }
        }
        else if (result.dismiss === Swal.DismissReason.cancel) {
            return showCancelledAlert(Swal);
        }
    }));
};