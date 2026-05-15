export interface Game {
  id: number;
  name: string;
  votes: number;
  steam_app_id?: number;
  steam_image_url?: string | null;
}

export interface DailyAction {
  action: 'vote' | 'add';
  game_id: number;
  at: string;
}

export interface GamesResponse {
  games: Game[];
  daily_action: DailyAction | null;
  last_game_id: number;
}

export interface HealthResponse {
  status: 'ok' | 'degraded' | 'error';
  api: boolean;
}

export interface SteamSearchItem {
  type: string;
  name: string;
  id: number;
  price?: {
    currency: string;
    initial: number;
    final: number;
  };
  tiny_image: string;
  metascore?: string;
  platforms?: {
    windows?: boolean;
    mac?: boolean;
    linux?: boolean;
  };
  controller_support?: string;
}

export interface BoardGameItem {
  id: number;
  name: string;
  category: string;
  year: number;
  image_url: string;
}

export interface ApiError {
  error: string;
}
