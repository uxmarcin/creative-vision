// Adapter: litery eksportów jak w bundlu Framera -> React
import * as R from 'react';
import { jsx, jsxs, Fragment } from 'react/jsx-runtime';
import { createPortal } from 'react-dom';
const init = () => {};
const win = typeof document !== 'undefined' ? globalThis.window : undefined;
const nav = typeof document !== 'undefined' ? globalThis.navigator : undefined;
const A = R.useMemo, D = R.useCallback, E = R.useSyncExternalStore, M = R.startTransition,
  N = R.Fragment, P = R.cloneElement, S = R.useId, T = R.useLayoutEffect, _ = R.useDeferredValue,
  d = R.createElement, f = R.Component, g = R.useState, h = R.Suspense, j = R.useInsertionEffect,
  k = R.useContext, m = R.isValidElement, p = R.forwardRef, u = R.createContext, v = R.useEffect,
  w = R.Children, x = R.memo, y = R.useRef;
export {
  A, init as C, D, E, init as F, nav as I, win as L, M, N, R as O, P, S, T, _, init as a,
  Fragment as c, d, f, g, h, createPortal as i, j, k, jsxs as l, m, init as n, jsx as o, p,
  init as s, u, v, w, x, y,
};
