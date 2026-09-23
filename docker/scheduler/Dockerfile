FROM sail-8.5/app

RUN apt-get update \
    && apt-get install -y cron \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY crontab /etc/cron.d/reading-plans-schedule
RUN chmod 0644 /etc/cron.d/reading-plans-schedule \
    && crontab /etc/cron.d/reading-plans-schedule

CMD ["cron", "-f"]
