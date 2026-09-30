/**
 * Video Player Controller
 * Manages interactive video playback with custom controls
 */

// Open video modal
export function openVideoModal(trigger: HTMLElement): void {
	const videoSrc = trigger.dataset.videoSrc;
	const videoTitle = trigger.dataset.videoTitle;
	
	if (!videoSrc) return;
	
	const modal = document.getElementById('video-modal');
	const video = document.getElementById('modal-video-player') as HTMLVideoElement;
	
	if (modal && video) {
		video.src = videoSrc;
		video.title = videoTitle || '';
		video.muted = false;
		
		modal.classList.remove('hidden');
		modal.classList.add('flex');
		
		// Play video
		// Autoplay is blocked by policy in plenty of browsers; nothing to do.
			void video.play().catch(() => {});
		
		// Prevent body scroll
		document.body.style.overflow = 'hidden';
		
		// Setup progress tracking
		video.addEventListener('timeupdate', () => updateProgress(video));
		video.addEventListener('loadedmetadata', () => updateProgress(video));
	}
}

// Close video modal
export function closeVideoModal(event?: Event): void {
	if (event) {
		event.stopPropagation();
	}
	
	const modal = document.getElementById('video-modal');
	const video = document.getElementById('modal-video-player') as HTMLVideoElement;
	
	if (modal && video) {
		// Pause and reset video
		video.pause();
		video.currentTime = 0;
		video.src = '';
		
		modal.classList.add('hidden');
		modal.classList.remove('flex');
		
		// Restore body scroll
		document.body.style.overflow = '';
	}
}

// Seek video to specific time on progress bar click
export function seekVideo(event: MouseEvent, progressContainer: HTMLElement): void {
	event.stopPropagation();
	const container = progressContainer.closest('.group');
	const video = container?.querySelector('video');
	
	if (video && video.duration) {
		const rect = progressContainer.getBoundingClientRect();
		const clickX = event.clientX - rect.left;
		const progress = clickX / rect.width;
		video.currentTime = progress * video.duration;
	}
}

// Toggle play/pause state
export function togglePlayPause(video: HTMLVideoElement): void {
	const container = video.closest('.group');
	const playIcon = container?.querySelector('.video-play-icon');
	const playSvg = playIcon?.querySelector('.play-icon');
	const pauseSvg = playIcon?.querySelector('.pause-icon');
	
	if (video.paused) {
		video.play();
		playSvg?.classList.add('hidden');
		pauseSvg?.classList.remove('hidden');
	} else {
		video.pause();
		playSvg?.classList.remove('hidden');
		pauseSvg?.classList.add('hidden');
	}
	
	// Show icon for 500ms
	playIcon?.classList.remove('opacity-0');
	playIcon?.classList.add('opacity-100');
	setTimeout(() => {
		playIcon?.classList.remove('opacity-100');
		playIcon?.classList.add('opacity-0');
	}, 500);
}

// Toggle mute/unmute state
export function toggleMute(button: HTMLElement): void {
	const container = button.closest('.group');
	const video = container?.querySelector('video');
	const muteIcon = button.querySelector('.mute-icon');
	const unmuteIcon = button.querySelector('.unmute-icon');
	
	if (video) {
		if (video.muted) {
			video.muted = false;
			muteIcon?.classList.add('hidden');
			unmuteIcon?.classList.remove('hidden');
		} else {
			video.muted = true;
			muteIcon?.classList.remove('hidden');
			unmuteIcon?.classList.add('hidden');
		}
	}
}

// Update progress bar based on video current time
function updateProgress(video: HTMLVideoElement): void {
	const progressBar = video.closest('.group')?.querySelector('.video-progress') as HTMLElement;
	if (progressBar && video.duration) {
		const progress = video.currentTime / video.duration;
		progressBar.style.transform = `scaleX(${progress})`;
	}
}

// Initialize video player with Intersection Observer for lazy loading
function initVideoPlayer(): void {
	if (!('IntersectionObserver' in window)) {
		return;
	}

	const videoObserver = new IntersectionObserver((entries) => {
		entries.forEach(entry => {
			const video = entry.target as HTMLVideoElement;
			if (entry.isIntersecting) {
				// Video in viewport - play
				void video.play().catch(() => {});
			} else {
				// Video out of viewport - pause
				video.pause();
			}
		});
	}, {
		threshold: 0.5, // Trigger when 50% of video is visible
		rootMargin: '0px'
	});

	// Apply observer to all lazy-load videos
	document.querySelectorAll<HTMLVideoElement>('.video-lazy').forEach(video => {
		videoObserver.observe(video);
		
		// Listen for progress updates
		video.addEventListener('timeupdate', () => updateProgress(video));
		video.addEventListener('loadedmetadata', () => updateProgress(video));
	});
}

// Make functions globally available for inline onclick handlers
declare global {
	interface Window {
		seekVideo: typeof seekVideo;
		togglePlayPause: typeof togglePlayPause;
		toggleMute: typeof toggleMute;
		openVideoModal: typeof openVideoModal;
		closeVideoModal: typeof closeVideoModal;
	}
}

window.seekVideo = seekVideo;
window.togglePlayPause = togglePlayPause;
window.toggleMute = toggleMute;
window.openVideoModal = openVideoModal;
window.closeVideoModal = closeVideoModal;

// Initialize on DOM ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initVideoPlayer);
} else {
	initVideoPlayer();
}
