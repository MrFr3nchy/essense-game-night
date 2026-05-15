import { AnimatePresence, motion } from 'framer-motion';
import type { DailyAction, Game } from '../types';
import { GameCard } from './GameCard';

interface GameListProps {
  games: Game[];
  dailyAction: DailyAction | null;
  onVote: (id: number) => Promise<void>;
  onRemoveVote: (id: number) => Promise<void>;
  onRemove: (id: number, name: string) => void;
}

export function GameList({
  games,
  dailyAction,
  onVote,
  onRemoveVote,
  onRemove,
}: GameListProps) {
  if (games.length === 0) {
    return (
      <div className="empty-state">
        <p className="empty-state__text">
          No games yet. Be the first to suggest one!
        </p>
      </div>
    );
  }

  return (
    <div className="game-list">
      <AnimatePresence mode="popLayout">
        {games.map((game, index) => (
          <motion.div
            key={game.id}
            layout
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, x: 80 }}
            transition={{
              layout: { type: 'spring', stiffness: 350, damping: 30 },
              opacity: { duration: 0.25 },
              y: { duration: 0.25 },
              x: { duration: 0.2 },
            }}
          >
            <GameCard
              game={game}
              rank={index + 1}
              dailyAction={dailyAction}
              onVote={onVote}
              onRemoveVote={onRemoveVote}
              onRemove={onRemove}
            />
          </motion.div>
        ))}
      </AnimatePresence>
    </div>
  );
}
