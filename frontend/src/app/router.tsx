import { createBrowserRouter, Navigate } from 'react-router-dom';
import { ProtectedRoute } from '@/lib/auth';
import { AdminLoginPage } from '@/app/routes/auth/AdminLoginPage';
import { JudgePinLoginPage } from '@/app/routes/auth/JudgePinLoginPage';
import { EventSetupPage } from '@/app/routes/console/EventSetupPage';
import { TabulatorConsolePage } from '@/app/routes/console/TabulatorConsolePage';
import { ArenaLedWallPage } from '@/app/routes/display/ArenaLedWallPage';
import { JudgeMatrixPage } from '@/app/routes/judge/JudgeMatrixPage';
import { JudgeStagePage } from '@/app/routes/judge/JudgeStagePage';
import { StageBroadcasterPage } from '@/app/routes/mc/StageBroadcasterPage';

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Navigate to="/login/judge" replace />,
  },
  {
    path: '/login/judge',
    element: <JudgePinLoginPage />,
  },
  {
    path: '/login/admin',
    element: <AdminLoginPage />,
  },
  {
    path: '/judge/stage',
    element: (
      <ProtectedRoute allowedActor="judge">
        <JudgeStagePage />
      </ProtectedRoute>
    ),
  },
  {
    path: '/judge/matrix',
    element: (
      <ProtectedRoute allowedActor="judge">
        <JudgeMatrixPage />
      </ProtectedRoute>
    ),
  },
  {
    path: '/console',
    element: (
      <ProtectedRoute allowedActor="user">
        <TabulatorConsolePage />
      </ProtectedRoute>
    ),
  },
  {
    path: '/console/setup',
    element: (
      <ProtectedRoute allowedActor="user">
        <EventSetupPage />
      </ProtectedRoute>
    ),
  },
  {
    path: '/mc',
    element: (
      <ProtectedRoute allowedActor="user">
        <StageBroadcasterPage />
      </ProtectedRoute>
    ),
  },
  {
    path: '/display',
    element: <ArenaLedWallPage />,
  },
  {
    path: '/display/:eventId',
    element: <ArenaLedWallPage />,
  },
  {
    path: '*',
    element: <Navigate to="/login/judge" replace />,
  },
]);
