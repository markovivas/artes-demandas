(function () {
	"use strict";

	function formatPhone(value) {
		const digits = value.replace(/\D/g, "").slice(0, 11);

		if (digits.length <= 2) {
			return digits.length ? "(" + digits : "";
		}

		if (digits.length <= 7) {
			return "(" + digits.slice(0, 2) + ") " + digits.slice(2);
		}

		if (digits.length <= 10) {
			return "(" + digits.slice(0, 2) + ") " + digits.slice(2, 6) + "-" + digits.slice(6);
		}

		return "(" + digits.slice(0, 2) + ") " + digits.slice(2, 7) + "-" + digits.slice(7);
	}

	document.addEventListener("DOMContentLoaded", function () {
		const phoneInputs = document.querySelectorAll(".arte-phone-input");

		phoneInputs.forEach(function (input) {
			input.addEventListener("input", function () {
				input.value = formatPhone(input.value);
			});

			if (input.value) {
				input.value = formatPhone(input.value);
			}
		});
	});
})();
