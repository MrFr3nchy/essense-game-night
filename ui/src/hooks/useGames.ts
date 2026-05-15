import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../api';
import type { DailyAction, Game } from '../types';

export function useGames() {
  const [games, setGames] = useState<Game[]>([]);
  const [dailyAction, setDailyAction] = useState<DailyAction | null>(null);
  const [lastGameId, setLastGameId] = useState(0);
  const [loading, setLoading] = useState(true);
  const [fetching, setFetching] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const initialLoadDone = useRef(false);

  const fetchGames = useCallback(async (isAction = false) => {
    if (isAction) {
      setFetching(true);
    }
    try {
      if (!isAction) setError(null);
      const data = await api.listGames();
      setGames(data.games);
      setDailyAction(data.daily_action);
      setLastGameId(data.last_game_id ?? 0);
    } catch (err) {
      if (!isAction) {
        setError(err instanceof Error ? err.message : 'Failed to load games.');
      }
    } finally {
      if (!initialLoadDone.current) {
        initialLoadDone.current = true;
        setLoading(false);
      }
      if (isAction) setFetching(false);
    }
  }, []);

  useEffect(() => {
    fetchGames();
  }, [fetchGames]);

  const addGame = async (payload: { name: string; steamAppId: number; imageUrl?: string }) => {
    const result = await api.addGame(payload.name, payload.steamAppId, payload.imageUrl);
    await fetchGames(true);
    return result;
  };

  const vote = async (id: number) => {
    await api.vote(id);
    await fetchGames(true);
  };

  const removeVote = async (id: number) => {
    await api.removeVote(id);
    await fetchGames(true);
  };

  const removeGame = async (id: number) => {
    await api.removeGame(id);
    await fetchGames(true);
  };

  const resetLibrary = async () => {
    await api.resetLibrary();
    await fetchGames(true);
  };

  return {
    games,
    dailyAction,
    lastGameId,
    loading,
    fetching,
    error,
    addGame,
    vote,
    removeVote,
    removeGame,
    resetLibrary,
    refresh: () => fetchGames(),
  };
}
