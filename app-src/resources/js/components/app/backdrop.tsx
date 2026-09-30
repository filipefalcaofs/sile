import { AnimatePresence, motion } from 'motion/react';
import { useSidebar } from '@/contexts/sidebar-context';
import { backdropFade } from '@/lib/motion';

export default function Backdrop() {
    const { isMobileOpen, toggleMobileSidebar } = useSidebar();

    return (
        <AnimatePresence>
            {isMobileOpen && (
                <motion.div
                    className="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
                    onClick={toggleMobileSidebar}
                    initial={backdropFade.initial}
                    animate={backdropFade.animate}
                    exit={backdropFade.exit}
                />
            )}
        </AnimatePresence>
    );
}
