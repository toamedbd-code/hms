import { router } from '@inertiajs/vue3';
import toastr from 'toastr';
import 'toastr/build/toastr.css';
import Swal from 'sweetalert2';


toastr.options = {
    closeButton: true,
    progressBar: true,
};

const swalWithBootstrapButtons = Swal.mixin({
    customClass: {
        confirmButton: "btn btn-success",
        cancelButton: "btn btn-danger"
    },
    buttonsStyling: false
});

export const successMessage = (message) => {
    toastr.success(message);
};

export const infoMessage = (message) => {
    toastr.info(message);
};

export const warningMessage = (message) => {
    toastr.warning(message);
};

export const errorMessage = (message) => {
    toastr.error(message);
};

export const displayWarning = (errors) => {
    const errorKeys = Object.keys(errors);

    errorKeys.forEach((errorKey) => {
        warningMessage(errors[errorKey]);
    });
};


export const displayResponse = (response) => {
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
    Swal.fire({
        title: title,
        text: message,
        icon: "success"
    });
};
export const failAlert = (title, message) => {
    Swal.fire({
        title: title,
        text: message,
        icon: "error"
    });
};



export const statusChangeConfirmation = (url) => {
    Swal.fire({
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
        else if (
            result.dismiss === Swal.DismissReason.cancel
        ) {
            swalWithBootstrapButtons.fire({
                title: "Cancelled",
                text: "Your imaginary data is safe :)",
                icon: "error"
            });
        }
    });
};

export const deleteConfirmation = (url) => {
    Swal.fire({
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
        else if (
            result.dismiss === Swal.DismissReason.cancel
        ) {
            swalWithBootstrapButtons.fire({
                title: "Cancelled",
                text: "Your imaginary data is safe :)",
                icon: "error"
            });
        }
    });
};