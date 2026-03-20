document.addEventListener('DOMContentLoaded', () => {
	const mobileMedia = window.matchMedia('(max-width: 640px)');

	const createViewer = (element, images, options = {}) => {
		if (!window.OpenSeadragon || !element || !images.length) {
			return null;
		}

		let naturalWidth = 0;
		let naturalHeight = 0;

		const applyViewerSizing = () => {
			if (!naturalWidth || !naturalHeight) {
				return;
			}

			element.style.setProperty('--wj-viewer-ratio', `${naturalWidth} / ${naturalHeight}`);

			if (!mobileMedia.matches || options.forceModal) {
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

		const loadDimensions = (imageSrc) => {
			const preload = new window.Image();
			preload.addEventListener('load', () => {
				if (preload.naturalWidth && preload.naturalHeight) {
					naturalWidth = preload.naturalWidth;
					naturalHeight = preload.naturalHeight;
					applyViewerSizing();
				}
			});
			preload.src = imageSrc;
		};

		loadDimensions(images[0]);

		const viewer = OpenSeadragon({
			id: element.dataset.viewerId || element.id,
			prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@5.0.1/build/openseadragon/images/',
			showNavigator: false,
			showNavigationControl: !mobileMedia.matches || options.forceModal,
			sequenceMode: false,
			showRotationControl: false,
			constrainDuringPan: true,
			visibilityRatio: 1,
			minZoomImageRatio: mobileMedia.matches && !options.forceModal ? 0.9 : 0.1,
			maxZoomPixelRatio: mobileMedia.matches ? 2.5 : 3,
			defaultZoomLevel: 0,
			homeFillsViewer: false,
			animationTime: mobileMedia.matches ? 0.9 : 1.2,
			springStiffness: mobileMedia.matches ? 6.5 : 7,
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

		window.addEventListener('resize', applyViewerSizing);

		return {
			viewer,
			openImage(imageSrc) {
				loadDimensions(imageSrc);
				viewer.open({
					...(window.wjTheme?.viewerTileSource || {}),
					url: imageSrc,
				});
			},
			goHome() {
				viewer.viewport.goHome(true);
			},
		};
	};

	document.querySelectorAll('[data-wj-viewer-shell]').forEach((shell) => {
		const inlineElement = shell.querySelector('[data-wj-viewer]');
		if (!inlineElement) {
			return;
		}

		let images = [];
		try {
			images = JSON.parse(inlineElement.dataset.images || '[]');
		} catch (error) {
			images = [];
		}

		if (!images.length) {
			return;
		}

		let inlineInstance = null;
		if (!mobileMedia.matches) {
			inlineInstance = createViewer(inlineElement, images);
		}

		shell.querySelectorAll('[data-wj-thumb]').forEach((button) => {
			button.addEventListener('click', () => {
				if (!inlineInstance) {
					return;
				}

				const imageSrc = button.dataset.imageSrc;
				if (!imageSrc) {
					return;
				}

				inlineInstance.openImage(imageSrc);
			});
		});

		const modal = document.getElementById(`${inlineElement.dataset.viewerId}-modal`);
		const modalImage = modal?.querySelector('[data-wj-modal-image]');
		const modalCount = modal?.querySelector('[data-wj-modal-count]');
		const modalPrev = modal?.querySelector('[data-wj-modal-prev]');
		const modalNext = modal?.querySelector('[data-wj-modal-next]');
		let modalIndex = 0;

		const renderModalState = () => {
			if (!modalCount) {
				return;
			}

			modalCount.textContent = `Image ${modalIndex + 1} of ${images.length}`;
		};

		const setActiveModalImage = (imageSrc, activeButton = null) => {
			if (!modalImage || !imageSrc) {
				return;
			}

			const foundIndex = images.indexOf(imageSrc);
			if (foundIndex >= 0) {
				modalIndex = foundIndex;
			}

			modalImage.src = imageSrc;
			renderModalState();

			modal?.querySelectorAll('[data-wj-modal-thumb]').forEach((button) => {
				const isActive = button === activeButton || button.dataset.imageSrc === imageSrc;
				button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
			});
		};

		modal?.querySelectorAll('[data-wj-modal-thumb]').forEach((button) => {
			button.addEventListener('click', () => {
				const imageSrc = button.dataset.imageSrc;
				if (!imageSrc) {
					return;
				}

				setActiveModalImage(imageSrc, button);
			});
		});

		modalPrev?.addEventListener('click', () => {
			modalIndex = (modalIndex - 1 + images.length) % images.length;
			setActiveModalImage(images[modalIndex]);
		});

		modalNext?.addEventListener('click', () => {
			modalIndex = (modalIndex + 1) % images.length;
			setActiveModalImage(images[modalIndex]);
		});

		const openModal = (startIndex = 0) => {
			if (!modal) {
				return;
			}

			modal.hidden = false;
			document.documentElement.classList.add('wj-modal-open');
			modalIndex = Math.max(0, Math.min(startIndex, images.length - 1));
			setActiveModalImage(images[modalIndex]);
		};

		const closeModal = () => {
			if (!modal) {
				return;
			}

			modal.hidden = true;
			document.documentElement.classList.remove('wj-modal-open');
		};

		shell.querySelectorAll('[data-wj-modal-open]').forEach((button) => {
			button.addEventListener('click', () => openModal());
		});

		shell.querySelectorAll('[data-wj-thumb]').forEach((button) => {
			if (!mobileMedia.matches) {
				return;
			}

			button.addEventListener('click', () => {
				const imageSrc = button.dataset.imageSrc;
				if (!imageSrc) {
					return;
				}

				const index = images.indexOf(imageSrc);
				openModal(index >= 0 ? index : 0);
			});
		});

		modal?.querySelectorAll('[data-wj-modal-close]').forEach((button) => {
			button.addEventListener('click', closeModal);
		});

		modal?.addEventListener('click', (event) => {
			if (event.target === modal) {
				closeModal();
			}
		});

		window.addEventListener('keydown', (event) => {
			if ('Escape' === event.key && modal && !modal.hidden) {
				closeModal();
			}
		});
	});
});
