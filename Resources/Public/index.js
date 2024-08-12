window.addEventListener('load', function () {
	document.querySelectorAll('.neos-two-factor__secret-wrapper')
		.forEach(function (secretFormElement) {
			const secretInput = secretFormElement.querySelector('input#secret')
			const secretDialog = secretFormElement.querySelector('dialog')
			const showSecretButton = secretFormElement.querySelector('.neos-two-factor__secret__show__button')
			const secretDialogCloseButton = secretDialog.querySelector('.neos-two-factor__secret__close__button')

			showSecretButton.onclick = function () {
				secretDialog.showModal()
			}

			secretDialogCloseButton.onclick = function () {
				secretDialog.close()
			}

			const secretDisplay = secretDialog.querySelector('.neos-two-factor__secret')
			const allChars = Array.from(secretDisplay.querySelectorAll('span'))
			const firstChar = allChars[0]
			const lastChar = allChars[allChars.length - 1]
			const overflowIndicatorLeft = secretDialog.querySelector('.neos-two-factor__secret__overflow-indicator--left')
			const overflowIndicatorRight = secretDialog.querySelector('.neos-two-factor__secret__overflow-indicator--right')

			const intersectionObserverFirstChar = new IntersectionObserver(function (entries) {
				if (entries[0].isIntersecting) {
					overflowIndicatorLeft.classList.add('neos-two-factor__hidden')
				} else {
					overflowIndicatorLeft.classList.remove('neos-two-factor__hidden')
				}
			})

			const intersectionObserverLastChar = new IntersectionObserver(function (entries) {
				if (entries[0].isIntersecting) {
					overflowIndicatorRight.classList.add('neos-two-factor__hidden')
				} else {
					overflowIndicatorRight.classList.remove('neos-two-factor__hidden')
				}
			})

			intersectionObserverFirstChar.observe(firstChar)
			intersectionObserverLastChar.observe(lastChar)

			const copySecretButton = secretFormElement.querySelector('.neos-two-factor__secret__copy__button')
			copySecretButton.onclick = function () {

				navigator.clipboard.writeText(secretInput.value)
					.then(function () {
						copySecretButton.querySelectorAll('span').forEach(element => {
							element.classList.toggle('neos-two-factor__hidden')
						})

						return new Promise(function (resolve) {
							setTimeout(resolve, 1000)
						})
					})
					.then(function () {
						copySecretButton.querySelectorAll('span').forEach(element => {
							element.classList.toggle('neos-two-factor__hidden')
						})
					})
			}
		})
})
