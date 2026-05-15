import type { BoardGameItem, GamesResponse, HealthResponse, SteamSearchItem } from './types';

const BASE_URL = import.meta.env.VITE_API_URL || '/api';

class ApiClient {
  private async request<T>(
    path: string,
    options: RequestInit = {},
    signal?: AbortSignal,
  ): Promise<T> {
    const url = `${BASE_URL}${path}`;

    const response = await fetch(url, {
      credentials: 'include',
      signal,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...options.headers,
      },
      ...options,
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || data.message || 'Something went wrong.');
    }

    return data as T;
  }

  listGames() {
    return this.request<GamesResponse>('/games');
  }

  addGame(name: string, steamAppId: number = 0, imageUrl?: string) {
    return this.request<{ id: number; message: string }>('/games', {
      method: 'POST',
      body: JSON.stringify({
        name,
        steam_app_id: steamAppId || undefined,
        image_url: imageUrl || undefined,
      }),
    });
  }

  vote(id: number) {
    return this.request<{ message: string }>(`/games/${id}/vote`, {
      method: 'POST',
    });
  }

  removeVote(id: number) {
    return this.request<{ message: string }>(`/games/${id}/vote`, {
      method: 'DELETE',
    });
  }

  removeGame(id: number) {
    return this.request<{ message: string }>(`/games/${id}`, {
      method: 'DELETE',
    });
  }

  health() {
    return this.request<HealthResponse>('/health');
  }

  resetLibrary() {
    return this.request<{ message: string }>('/reset', { method: 'POST' });
  }

  searchSteam(term: string, signal?: AbortSignal) {
    return this.request<{ items: SteamSearchItem[] }>(
      `/steam/search?term=${encodeURIComponent(term)}`,
      {},
      signal,
    );
  }

  searchBoardGames(term: string, signal?: AbortSignal) {
    return this.request<{ items: BoardGameItem[] }>(
      `/board-games/search?term=${encodeURIComponent(term)}`,
      {},
      signal,
    );
  }
}

export const api = new ApiClient();
