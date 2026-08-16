import { Button } from '@/components/ui/button';

type Props = {
    providers: string[];
};

export default function OauthButtons({ providers }: Props) {
    if (providers.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            {providers.includes('github') && (
                <Button variant="outline" className="w-full" asChild>
                    <a href="/auth/github/redirect">Continue with GitHub</a>
                </Button>
            )}
            {providers.includes('google') && (
                <Button variant="outline" className="w-full" asChild>
                    <a href="/auth/google/redirect">Continue with Google</a>
                </Button>
            )}
            <div className="relative my-2">
                <div className="absolute inset-0 flex items-center">
                    <span className="w-full border-t" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-background px-2 text-muted-foreground">
                        or continue with email
                    </span>
                </div>
            </div>
        </div>
    );
}
