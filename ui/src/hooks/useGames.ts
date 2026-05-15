import { useCallback, useEffect, useState } from 'react';
import { api } from '../api';
import type { DailyAction, Game } from '../types';

export function useGames() {
  const [games, setGames] = useState<Game[]>([]);
  const [dailyAction, setDailyAction] = useState<DailyAction | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchGames = useCallback(async () => {
    try {
      setError(null);
      const data = await api.listGames();
      setGames(data.games);
      setDailyAction(data.daily_action);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load games.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchGames();
  }, [fetchGames]);

  const addGame = async (payload: { name: string; steamAppId: number }) => {
    const result = await api.addGame(payload.name, payload.steamAppId);
    await fetchGames();
    return result;
  };

  const vote = async (id: number) => {
    await api.vote(id);
    await fetchGames();
  };

  const removeVote = async (id: number) => {
    await api.removeVote(id);
    await fetchGames();
  };

  const removeGame = async (id: number) => {
    await api.removeGame(id);
    await fetchGames();
  };

  return {
    games,
    dailyAction,
    loading,
    error,
    addGame,
    vote,
    removeVote,
    removeGame,
    refresh: fetchGames,
  };
}
