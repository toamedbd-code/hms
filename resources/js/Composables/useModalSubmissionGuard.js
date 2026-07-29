import { ref } from "vue";

export const useModalSubmissionGuard = (prefix = "submit") => {
  const isSubmitting = ref(false);
  const submissionToken = ref("");

  const generateToken = () => {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
  };

  const prepareSubmissionToken = () => {
    submissionToken.value = generateToken();
    return submissionToken.value;
  };

  const ensureSubmissionToken = () => {
    return submissionToken.value || prepareSubmissionToken();
  };

  const beginSubmission = () => {
    isSubmitting.value = true;
    ensureSubmissionToken();
  };

  const endSubmission = () => {
    isSubmitting.value = false;
  };

  const resetSubmissionToken = () => {
    submissionToken.value = "";
  };

  return {
    isSubmitting,
    submissionToken,
    prepareSubmissionToken,
    ensureSubmissionToken,
    beginSubmission,
    endSubmission,
    resetSubmissionToken,
  };
};
