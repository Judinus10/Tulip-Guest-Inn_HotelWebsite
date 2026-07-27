import { Link } from 'react-router-dom';

export default function NotFound() {
  return (
    <main className="min-h-[70vh] bg-background flex items-center justify-center px-4 pt-24">
      <div className="max-w-xl text-center">
        <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-4">404 Error</p>
        <h1 className="font-serif text-5xl md:text-6xl font-light text-dark mb-5">Page Not Found</h1>
        <div className="w-14 h-px bg-gold mx-auto mb-6" />
        <p className="text-sm text-gray-500 leading-relaxed mb-8">
          The page you requested does not exist or may have moved. Return home or browse our available rooms.
        </p>
        <div className="flex flex-wrap justify-center gap-4">
          <Link to="/" className="btn-primary">Return Home</Link>
          <Link to="/rooms" className="btn-outline">View Rooms</Link>
        </div>
      </div>
    </main>
  );
}
