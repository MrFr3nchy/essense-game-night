import { useEffect, useRef, useState } from 'react';
import { motion, useAnimate } from 'framer-motion';
import type { DailyAction, Game } from '../types';

interface GameCardProps {
  game: Game;
  rank: number;
  dailyAction: DailyAction | null;
  onVote: (id: number) => Promise<void>;
  onRemoveVote: (id: number) => Promise<void>;
  onRemove: (id: number, name: string) => void;
}

const RANK_CLASS: Record<number, string> = {
  1: 'game-card__rank--gold',
  2: 'game-card__rank--silver',
  3: 'game-card__rank--bronze',
};

export function GameCard({
  game,
  rank,
  dailyAction,
  onVote,
  onRemoveVote,
  onRemove,
}: GameCardProps) {
  const [acting, setActing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [votesRef, animateVotes] = useAnimate<HTMLSpanElement>();
  const [rankRef, animateRank] = useAnimate<HTMLDivElement>();

  const prevVotes = useRef(game.votes);
  const prevRank = useRef(rank);
  const isInitial = useRef(true);

  useEffect(() => {
    if (isInitial.current) {
      isInitial.current = false;
      return;
    }

    if (game.votes !== prevVotes.current) {
      prevVotes.current = game.votes;
      animateVotes(votesRef.current, { scale: [1, 1.4, 1] }, { duration: 0.3 });
    }

    if (rank < prevRank.current) {
      prevRank.current = rank;
      animateRank(rankRef.current, { scale: [1, 1.35, 1] }, { duration: 0.35, ease: 'easeOut' });
    } else {
      prevRank.current = rank;
    }
  }, [game.votes, rank, animateVotes, animateRank, votesRef, rankRef]);

  const votedForThis =
    dailyAction?.action === 'vote' && dailyAction.game_id === game.id;

  const hasActedToday = dailyAction !== null;
  const canVote = !hasActedToday || votedForThis;

  const handleAction = async (action: () => Promise<void>) => {
    setActing(true);
    setError(null);
    try {
      await action();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Action failed.');
    } finally {
      setActing(false);
    }
  };


  return (
    <div className={`game-card ${votedForThis ? 'game-card--voted' : ''}`}>
      <motion.div
        ref={rankRef}
        className={`game-card__rank ${RANK_CLASS[rank] ?? ''}`}
      >
        {rank}
      </motion.div>

      <div className="game-card__image-wrapper">
        {game.steam_image_url ? (
          <img
            className="game-card__image"
            src={game.steam_image_url}
            alt={game.name}
            loading="lazy"
            referrerPolicy="no-referrer"
          />
        ) : (
          <div className="game-card__image-placeholder">
            <span>🎮</span>
          </div>
        )}
      </div>

      <div className="game-card__content">
        <div className="game-card__info">
          <h3 className="game-card__name">{game.name}</h3>
          <div className={`game-card__votes ${votedForThis ? 'game-card__votes--active' : ''}`}>
            <span className="game-card__votes-arrow">▲</span>
            <motion.span ref={votesRef} className="game-card__votes-count">
              {game.votes}
            </motion.span>
          </div>
        </div>

        <div className="game-card__actions">
          {votedForThis ? (
            <button
              className="btn btn--unvote"
              onClick={() => handleAction(() => onRemoveVote(game.id))}
              disabled={acting}
              title="Remove your vote"
            >
              {acting ? '…' : 'Unvote'}
            </button>
          ) : (
            <button
              className="btn btn--vote"
              onClick={() => handleAction(() => onVote(game.id))}
              disabled={acting || !canVote}
              title={canVote ? 'Cast your vote' : 'Daily action already used'}
            >
              {acting ? '…' : 'Vote'}
            </button>
          )}

          <button
            className="btn btn--remove-icon"
            onClick={() => onRemove(game.id, game.name)}
            disabled={acting}
            title="Remove game"
            aria-label="Remove game"
          >
            ✕
          </button>
        </div>
      </div>

      {error && <p className="game-card__error">{error}</p>}
    </div>
  );
}
