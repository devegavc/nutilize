const registerForm = document.getElementById("register-form");
const registerStep1 = document.getElementById("register-step-1");
const registerStep2 = document.getElementById("register-step-2");
const stepIndicator = document.getElementById("register-step-indicator");
const nextStepBtn = document.getElementById("next-step-btn");
const backStepBtn = document.getElementById("back-step-btn");
const firstNameInput = document.getElementById("first-name");
const middleInitialInput = document.getElementById("middle-initial");
const lastNameInput = document.getElementById("last-name");
const emailInput = document.getElementById("email");
const emailWarning = document.getElementById("email-warning");
const passwordInput = document.getElementById("password");
const confirmPasswordInput = document.getElementById("confirm-password");
const confirmPasswordWarning = document.getElementById("confirm-password-warning");
const passwordHelper = document.getElementById("password-helper");
const strengthBar = document.getElementById("password-strength-bar");
const strengthText = document.getElementById("password-strength-text");
const passwordValidIndicator = document.getElementById("password-valid-indicator");
const confirmPasswordValidIndicator = document.getElementById("confirm-password-valid-indicator");

let currentStep = registerForm && registerForm.dataset.startStep === "2" ? 2 : 1;

const rules = {
  length: {
    element: document.getElementById("rule-length"),
    test: (value) => value.length >= 8,
  },
  upper: {
    element: document.getElementById("rule-upper"),
    test: (value) => /[A-Z]/.test(value),
  },
  lower: {
    element: document.getElementById("rule-lower"),
    test: (value) => /[a-z]/.test(value),
  },
  number: {
    element: document.getElementById("rule-number"),
    test: (value) => /\d/.test(value),
  },
  special: {
    element: document.getElementById("rule-special"),
    test: (value) => /[^A-Za-z0-9]/.test(value),
  },
};

function updatePasswordStrength() {
  const value = passwordInput.value;
  const passedCount = Object.values(rules).reduce((count, rule) => {
    const passed = rule.test(value);
    rule.element.classList.toggle("passed", passed);
    return passed ? count + 1 : count;
  }, 0);

  const levels = ["weak", "weak", "fair", "good", "strong", "strong"];
  const labels = {
    weak: "Password strength: Weak",
    fair: "Password strength: Fair",
    good: "Password strength: Good",
    strong: "Password strength: Strong",
  };

  const currentLevel = levels[passedCount];
  strengthBar.className = "password-strength-bar " + currentLevel;
  strengthBar.style.width = (passedCount / 5) * 100 + "%";
  strengthText.textContent = labels[currentLevel];
  passwordHelper.classList.add("show");

  const isAcceptable = value.length > 0 && passedCount === 5;
  passwordValidIndicator.classList.toggle("show", isAcceptable);
}

function updateEmailWarning() {
  const value = emailInput.value.trim();
  const hasAt = value.includes("@");
  const invalid = value.length > 0 && (!hasAt || !emailInput.checkValidity());

  if (!invalid) {
    emailInput.classList.remove("is-invalid");
    emailWarning.classList.remove("show");
    emailWarning.textContent = "";
    return true;
  }

  emailInput.classList.add("is-invalid");
  emailWarning.classList.add("show");
  emailWarning.textContent = hasAt
    ? "Invalid email address. Please check the format."
    : "Email must include an @ symbol.";
  return false;
}

function updateConfirmPasswordMatch() {
  const passwordValue = passwordInput.value;
  const confirmValue = confirmPasswordInput.value;
  const hasValue = confirmValue.length > 0;
  const matches = hasValue && passwordValue === confirmValue;

  confirmPasswordValidIndicator.classList.toggle("show", matches);

  if (!hasValue || matches) {
    confirmPasswordInput.classList.remove("is-invalid");
    confirmPasswordWarning.classList.remove("show");
    confirmPasswordWarning.textContent = "";
    return true;
  }

  confirmPasswordInput.classList.add("is-invalid");
  confirmPasswordWarning.classList.add("show");
  confirmPasswordWarning.textContent = "Password confirmation does not match.";
  return false;
}

function validateStepOne() {
  if (!firstNameInput || !lastNameInput) {
    return true;
  }

  const firstNameValid = firstNameInput.value.trim().length > 0;
  const lastNameValid = lastNameInput.value.trim().length > 0;
  const middleInitialValid = !middleInitialInput || middleInitialInput.value.trim().length <= 1;

  firstNameInput.classList.toggle("is-invalid", !firstNameValid);
  lastNameInput.classList.toggle("is-invalid", !lastNameValid);

  if (middleInitialInput) {
    middleInitialInput.classList.toggle("is-invalid", !middleInitialValid);
  }

  return firstNameValid && lastNameValid && middleInitialValid;
}

function showRegisterStep(step) {
  if (!registerStep1 || !registerStep2 || !stepIndicator) {
    return;
  }

  currentStep = step;
  const onStepOne = step === 1;

  registerStep1.classList.toggle("d-none", !onStepOne);
  registerStep2.classList.toggle("d-none", onStepOne);
  stepIndicator.textContent = onStepOne ? "Step 1 of 2" : "Step 2 of 2";
}

if (nextStepBtn) {
  nextStepBtn.addEventListener("click", () => {
    if (validateStepOne()) {
      showRegisterStep(2);
    }
  });
}

if (backStepBtn) {
  backStepBtn.addEventListener("click", () => {
    showRegisterStep(1);
  });
}

if (middleInitialInput) {
  middleInitialInput.addEventListener("input", () => {
    middleInitialInput.value = middleInitialInput.value.replace(/[^A-Za-z]/g, "").slice(0, 1).toUpperCase();
  });
}

passwordInput.addEventListener("focus", () => {
  passwordHelper.classList.add("show");
  updatePasswordStrength();
});

passwordInput.addEventListener("input", updatePasswordStrength);
passwordInput.addEventListener("input", updateConfirmPasswordMatch);

passwordInput.addEventListener("blur", () => {
  passwordHelper.classList.remove("show");
});

confirmPasswordInput.addEventListener("input", updateConfirmPasswordMatch);
confirmPasswordInput.addEventListener("blur", updateConfirmPasswordMatch);

emailInput.addEventListener("input", updateEmailWarning);
emailInput.addEventListener("blur", updateEmailWarning);

registerForm.addEventListener("submit", (event) => {
  const stepOneValid = validateStepOne();
  const emailIsValid = updateEmailWarning();
  const passwordsMatch = updateConfirmPasswordMatch();

  if (!stepOneValid) {
    showRegisterStep(1);
    event.preventDefault();
    return;
  }

  if (currentStep !== 2) {
    showRegisterStep(2);
    event.preventDefault();
    return;
  }

  if (!emailIsValid || !passwordsMatch) {
    event.preventDefault();
  }
});

showRegisterStep(currentStep);
