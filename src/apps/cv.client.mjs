import { jsx } from 'react/jsx-runtime';
import { hydrateRoot } from 'react-dom/client';
import App from './cv.mjs';
hydrateRoot(document.getElementById('app'), jsx(App, JSON.parse(document.getElementById('cv-props').textContent)));
