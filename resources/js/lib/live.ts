/* Real time is optional: without a Reverb key the app keeps the one-minute refresh. */
export const LIVE = !!import.meta.env.VITE_REVERB_APP_KEY;
