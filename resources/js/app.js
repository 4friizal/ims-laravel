import Chart from "chart.js/auto";

window.Chart = Chart;

/**
 * Keep <input type="number"> digits-only.
 *
 * The native number input still accepts the scientific-notation characters
 * (e, E, +, -), which land in the field as letters and then submit as an empty
 * value. These guards cover every number input on the page, including those
 * Livewire renders later, so individual fields need no extra markup.
 */
const allowsDecimals = (input) => {
  // A missing step attribute means 1, i.e. whole numbers only.
  const step = input.getAttribute("step") ?? "1";

  return step === "any" || Number(step) % 1 !== 0;
};

const numberInput = (event) => {
  const el = event.target;

  return el instanceof HTMLInputElement && el.type === "number" ? el : null;
};

const isAllowed = (input, text) => (allowsDecimals(input) ? /^[0-9.]*$/ : /^[0-9]*$/).test(text);

document.addEventListener("keydown", (event) => {
  const input = numberInput(event);

  if (!input) {
    return;
  }

  // Let editing keys (Tab, arrows, Backspace…) and shortcuts through.
  if (event.key.length > 1 || event.ctrlKey || event.metaKey || event.altKey) {
    return;
  }

  // Reject a second decimal separator. A number input reads back as empty
  // once its value is invalid, so this only catches the well-formed case —
  // server-side validation stays the backstop for the rest.
  if (event.key === "." && input.value.includes(".")) {
    event.preventDefault();

    return;
  }

  if (!isAllowed(input, event.key)) {
    event.preventDefault();
  }
});

document.addEventListener("paste", (event) => {
  const input = numberInput(event);

  if (input && !isAllowed(input, event.clipboardData.getData("text").trim())) {
    event.preventDefault();
  }
});

document.addEventListener("drop", (event) => {
  const input = numberInput(event);

  if (input && !isAllowed(input, event.dataTransfer.getData("text").trim())) {
    event.preventDefault();
  }
});
