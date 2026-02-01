import { router } from "@inertiajs/vue3";
import toastr$1 from "toastr";
import Swal from "sweetalert2";
const toastr = "";
toastr$1.options = {
  closeButton: true,
  progressBar: true
};
const swalWithBootstrapButtons = Swal.mixin({
  customClass: {
    confirmButton: "btn btn-success",
    cancelButton: "btn btn-danger"
  },
  buttonsStyling: false
});
const successMessage = (message) => {
  toastr$1.success(message);
};
const warningMessage = (message) => {
  toastr$1.warning(message);
};
const errorMessage = (message) => {
  toastr$1.error(message);
};
const displayWarning = (errors) => {
  const errorKeys = Object.keys(errors);
  errorKeys.forEach((errorKey) => {
    warningMessage(errors[errorKey]);
  });
};
const displayResponse = (response) => {
  var _a, _b, _c, _d;
  if ((_b = (_a = response == null ? void 0 : response.props) == null ? void 0 : _a.flash) == null ? void 0 : _b.successMessage) {
    successMessage(response.props.flash.successMessage);
  } else if ((_d = (_c = response == null ? void 0 : response.props) == null ? void 0 : _c.flash) == null ? void 0 : _d.errorMessage) {
    errorMessage(response.props.flash.errorMessage);
  } else {
    errorMessage("Something went wrong. Please try again later.");
  }
};
const statusChangeConfirmation = (url) => {
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
        router.visit(url, {
          onSuccess: (response) => {
            displayResponse(response);
          },
          onError: (errorObject) => {
            displayWarning(errorObject);
          }
        });
      } catch (error) {
        errorMessage("An error occurred while changing status.");
      }
    } else if (result.dismiss === Swal.DismissReason.cancel) {
      swalWithBootstrapButtons.fire({
        title: "Cancelled",
        text: "Your imaginary data is safe :)",
        icon: "error"
      });
    }
  });
};
const deleteConfirmation = (url) => {
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
        router.delete(url, {
          onSuccess: (response) => {
            displayResponse(response);
          },
          onError: (errorObject) => {
            displayWarning(errorObject);
          }
        });
      } catch (error) {
        console.dir(error);
        errorMessage("An error occurred while changing status.");
      }
    } else if (result.dismiss === Swal.DismissReason.cancel) {
      swalWithBootstrapButtons.fire({
        title: "Cancelled",
        text: "Your imaginary data is safe :)",
        icon: "error"
      });
    }
  });
};
export {
  displayWarning as a,
  deleteConfirmation as b,
  displayResponse as d,
  statusChangeConfirmation as s
};
