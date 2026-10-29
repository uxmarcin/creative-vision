// Adapter: minimalne zamienniki API Framera (bez Framera)
const init = () => {};
const addPropertyControls = () => {};
const ControlType = new Proxy({}, { get: (_, k) => String(k) });
export { init as E, addPropertyControls as f, ControlType as r };
