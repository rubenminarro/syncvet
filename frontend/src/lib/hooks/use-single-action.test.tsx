import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useSingleAction } from './use-single-action';

describe('useSingleAction', () => {
  it('deja pasar un solo pedido hasta que se suelta', () => {
    const { result } = renderHook(() => useSingleAction());

    expect(result.current.tryStart()).toBe(true);
    expect(result.current.tryStart()).toBe(false);

    result.current.release();

    expect(result.current.tryStart()).toBe(true);
  });

  it('conserva el pedido en curso entre renders', () => {
    const { result, rerender } = renderHook(() => useSingleAction());

    expect(result.current.tryStart()).toBe(true);
    rerender();

    expect(result.current.tryStart()).toBe(false);
  });
});
