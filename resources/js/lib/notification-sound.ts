/**
 * A short two-note chime made with the Web Audio API: no file to load, and the same on every browser.
 * Browsers only allow sound after the person has interacted with the page, so the audio context is
 * created on the first click or key press and reused from then on.
 */
let ctx: AudioContext | null = null;

function context(): AudioContext | null {
    if (typeof window === 'undefined' || !('AudioContext' in window)) return null;
    ctx ??= new AudioContext();
    if (ctx.state === 'suspended') void ctx.resume();
    return ctx;
}

/** Call once at startup: unlocks audio on the first interaction so a later chime is allowed to play. */
export function armNotificationSound(): () => void {
    const unlock = () => {
        context();
        window.removeEventListener('pointerdown', unlock);
        window.removeEventListener('keydown', unlock);
    };
    window.addEventListener('pointerdown', unlock, { once: true });
    window.addEventListener('keydown', unlock, { once: true });
    return unlock;
}

export function playNotificationSound(): void {
    const c = context();
    if (!c || c.state !== 'running') return;
    const t = c.currentTime;
    for (const [freq, at] of [
        [880, 0],
        [1174.66, 0.14],
    ] as const) {
        const osc = c.createOscillator();
        const gain = c.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, t + at);
        gain.gain.exponentialRampToValueAtTime(0.18, t + at + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + at + 0.35);
        osc.connect(gain).connect(c.destination);
        osc.start(t + at);
        osc.stop(t + at + 0.4);
    }
}
