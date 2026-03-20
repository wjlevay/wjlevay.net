document.addEventListener('DOMContentLoaded', () => {
	const viewers = document.querySelectorAll('[data-wj-viewer]');
	const isSmallScreen = window.matchMedia('(max-width: 640px)').matches;

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

		let naturalWidth = 0;
		let naturalHeight = 0;

		const applyViewerSizing = () => {
			if (!naturalWidth || !naturalHeight) {
				return;
			}

			element.style.setProperty('--wj-viewer-ratio', `${naturalWidth} / ${naturalHeight}`);

			if (!window.matchMedia('(max-width: 640px)').matches) {
				element.style.removeProperty('height');
				return;
			}

			const ratio = naturalWidth / naturalHeight;
			const availableHeight = Math.min(window.innerHeight * 0.72, 540);
			const availableWidth = element.clientWidth || element.parentElement?.clientWidth || 0;
			if (!availableWidth || !ratio) {
				return;
			}

			const targetHeight = Math.min(availableHeight, availableWidth / ratio);
			element.style.height = `${Math.round(targetHeight)}px`;
		};

		const preload = new window.Image();
		preload.addEventListener('load', () => {
			if (preload.naturalWidth && preload.naturalHeight) {
				naturalWidth = preload.naturalWidth;
				naturalHeight = preload.naturalHeight;
				applyViewerSizing();
			}
		});
		preload.src = images[0];

		const viewer = OpenSeadragon({
			id: element.dataset.viewerId,
			prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@5.0.1/build/openseadragon/images/',
			showNavigator: false,
			showNavigationControl: !isSmallScreen,
			sequenceMode: false,
			showRotationControl: !isSmallScreen,
			constrainDuringPan: true,
			visibilityRatio: 1,
			minZoomImageRatio: isSmallScreen ? 0.9 : 0.1,
			maxZoomPixelRatio: isSmallScreen ? 2.5 : 3,
			defaultZoomLevel: 0,
			homeFillsViewer: false,
			animationTime: isSmallScreen ? 0.9 : 1.2,
			springStiffness: isSmallScreen ? 6.5 : 7,
			gestureSettingsTouch: {
				pinchRotate: false,
				clickToZoom: false,
				dblClickToZoom: true,
				flickEnabled: true,
			},
			tileSources: {
				...(window.wjTheme?.viewerTileSource || {}),
				url: images[0],
			},
		});

		viewer.addHandler('open', () => {
			applyViewerSizing();
			viewer.viewport.goHome(true);
		});

		window.addEventListener('resize', () => {
			applyViewerSizing();
		});

		const buttons = element.parentElement.querySelectorAll('[data-wj-thumb]');
		buttons.forEach((button) => {
			button.addEventListener('click', () => {
				const imageSrc = button.dataset.imageSrc;
				if (!imageSrc) {
					return;
				}

				const thumbPreload = new window.Image();
				thumbPreload.addEventListener('load', () => {
					if (thumbPreload.naturalWidth && thumbPreload.naturalHeight) {
						naturalWidth = thumbPreload.naturalWidth;
						naturalHeight = thumbPreload.naturalHeight;
						applyViewerSizing();
					}
				});
				thumbPreload.src = imageSrc;

				viewer.open({
					...(window.wjTheme?.viewerTileSource || {}),
					url: imageSrc,
				});
			});
		});
	});
});
