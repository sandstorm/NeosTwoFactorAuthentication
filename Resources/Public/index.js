// Progressively enhance the secret form element on load
window.addEventListener('load', function () {
	document.querySelectorAll('.neos-two-factor__secret-wrapper')
		.forEach(progressivelyEnhanceSecretFormElement)
})

function progressivelyEnhanceSecretFormElement(secretFormElement) {
	// Collect inner elements (scoped to the secretFormElement)
	const secretInput = secretFormElement.querySelector('input#secret')
	const secretDialog = secretFormElement.querySelector('dialog')
	const showSecretButton = secretFormElement.querySelector('.neos-two-factor__secret__show__button')
	const secretDialogCloseButton = secretDialog.querySelector('.neos-two-factor__secret__close__button')
	const secretDisplay = secretDialog.querySelector('.neos-two-factor__secret')

	// Init secret modal buttons
	showSecretButton.onclick = function () {
		secretDialog.showModal()
	}

	secretDialogCloseButton.onclick = function () {
		secretDialog.close()
	}

	// Init overflow indicators
	// Each character are wrapped in a span element (so we can have different styles for numbers and letters)
	const allCharElements = Array.from(secretDisplay.querySelectorAll('span'))
	const firstCharElement = allCharElements[0]
	const lastCharElement = allCharElements[allCharElements.length - 1]

	const overflowIndicatorLeft = secretDialog.querySelector('.neos-two-factor__secret__overflow-indicator--left')
	const overflowIndicatorRight = secretDialog.querySelector('.neos-two-factor__secret__overflow-indicator--right')

	const intersectionObserverOptions = {
		threshold: 0.9
	}

	const firstCharIntersectionObserver = new IntersectionObserver(function (entries) {
		// Hide or show indicator when first character is visible or not visible respectively
		if (entries[0].isIntersecting) {
			overflowIndicatorLeft.classList.add('neos-two-factor__hidden')
		} else {
			overflowIndicatorLeft.classList.remove('neos-two-factor__hidden')
		}
	}, intersectionObserverOptions)

	const lastCharIntersectionObserver = new IntersectionObserver(function (entries) {
		// Hide or show indicator when last character is visible or not visible respectively
		if (entries[0].isIntersecting) {
			overflowIndicatorRight.classList.add('neos-two-factor__hidden')
		} else {
			overflowIndicatorRight.classList.remove('neos-two-factor__hidden')
		}
	}, intersectionObserverOptions)

	firstCharIntersectionObserver.observe(firstCharElement)
	lastCharIntersectionObserver.observe(lastCharElement)

	// Init copy secret button
	const copySecretButton = secretFormElement.querySelector('.neos-two-factor__secret__copy__button')
	copySecretButton.onclick = async function () {
		try {
			// Copy secret to clipboard
			await navigator.clipboard.writeText(secretInput.value)
			// Disable button and show success indicator
			copySecretButton.setAttribute('disabled', 'disabled')
			copySecretButton.querySelectorAll('span').forEach(element => {
				element.classList.toggle('neos-two-factor__hidden')
			})

			// Wait for 1 second
			await new Promise(function (resolve) {
				setTimeout(resolve, 1000)
			})

		} finally {
			// Re-enable button and hide success indicator
			copySecretButton.removeAttribute('disabled')
			copySecretButton.querySelectorAll('span').forEach(element => {
				element.classList.toggle('neos-two-factor__hidden')
			})
		}
	}
}
