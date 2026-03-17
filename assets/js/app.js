document.addEventListener('DOMContentLoaded', () => {
	const viewers = document.querySelectorAll('[data-wj-viewer]');

	viewers.forEach((element) => {
		if (!window.OpenSeadragon) {
			return;
		}

		let images = [];

		try {
			images = JSON.parse(element.dataset.images || '[]');
		} catch (error) {
			images = [];
		}

		if (!images.length) {
			return;
		}

		const viewer = OpenSeadragon({
			id: element.dataset.viewerId,
			prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@5.0.1/build/openseadragon/images/',
			showNavigator: false,
			showNavigationControl: true,
			sequenceMode: false,
			showRotationControl: true,
			constrainDuringPan: true,
			visibilityRatio: 1,
			gestureSettingsTouch: {
				pinchRotate: true,
			},
			tileSources: {
				...(window.wjTheme?.viewerTileSource || {}),
				url: images[0],
			},
		});

		const buttons = element.parentElement.querySelectorAll('[data-wj-thumb]');
		buttons.forEach((button) => {
			button.addEventListener('click', () => {
				const imageSrc = button.dataset.imageSrc;
				if (!imageSrc) {
					return;
				}

				viewer.open({
					...(window.wjTheme?.viewerTileSource || {}),
					url: imageSrc,
				});
			});
		});
	});
});
